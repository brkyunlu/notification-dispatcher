<?php

namespace Tests\Unit\Services;

use App\Modules\Delivery\Contracts\ProviderInterface;
use App\Modules\Delivery\Exceptions\DeliveryException;
use App\Modules\Delivery\Services\CircuitBreakerService;
use App\Modules\Delivery\Services\DeliveryService;
use App\Modules\Delivery\Services\RateLimiterService;
use App\Modules\Notification\Models\Notification;
use App\Shared\Enums\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for DeliveryService
 * 
 * Tests notification delivery with circuit breaker and rate limiter integration.
 * Validates success/failure handling and provider interaction.
 */
class DeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProviderInterface $provider;
    private CircuitBreakerService $circuitBreaker;
    private RateLimiterService $rateLimiter;
    private DeliveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock dependencies
        $this->provider = $this->createMock(ProviderInterface::class);
        $this->circuitBreaker = $this->createMock(CircuitBreakerService::class);
        $this->rateLimiter = $this->createMock(RateLimiterService::class);
        
        $this->service = new DeliveryService(
            $this->provider,
            $this->circuitBreaker,
            $this->rateLimiter
        );
    }

    /** @test */
    public function it_delivers_notification_successfully()
    {
        $notification = Notification::factory()->email()->create();

        // Mock circuit breaker - allow
        $this->circuitBreaker
            ->expects($this->once())
            ->method('isAvailable')
            ->with(Channel::EMAIL)
            ->willReturn(true);

        // Mock rate limiter - allow
        $this->rateLimiter
            ->expects($this->once())
            ->method('waitForSlot')
            ->with(Channel::EMAIL);

        // Mock provider - success
        $this->provider
            ->expects($this->once())
            ->method('send')
            ->with($notification)
            ->willReturn(['message_id' => 'msg-123']);

        // Should record success in circuit breaker
        $this->circuitBreaker
            ->expects($this->once())
            ->method('recordSuccess')
            ->with(Channel::EMAIL);

        $result = $this->service->deliver($notification);

        $this->assertEquals(['message_id' => 'msg-123'], $result);
    }

    /** @test */
    public function it_throws_exception_when_circuit_breaker_open()
    {
        $notification = Notification::factory()->email()->create();

        // Mock circuit breaker - blocked
        $this->circuitBreaker
            ->expects($this->once())
            ->method('isAvailable')
            ->with(Channel::EMAIL)
            ->willReturn(false);

        // Should not attempt delivery
        $this->provider
            ->expects($this->never())
            ->method('send');

        $this->expectException(DeliveryException::class);

        $this->service->deliver($notification);
    }

    /** @test */
    public function it_throws_exception_when_rate_limit_exceeded()
    {
        $notification = Notification::factory()->email()->create();

        // Mock circuit breaker - allow
        $this->circuitBreaker
            ->expects($this->once())
            ->method('isAvailable')
            ->with(Channel::EMAIL)
            ->willReturn(true);

        // Mock rate limiter - blocked
        $this->rateLimiter
            ->expects($this->once())
            ->method('waitForSlot')
            ->with(Channel::EMAIL)
            ->willThrowException(DeliveryException::rateLimitExceeded('email'));

        // Should not attempt delivery
        $this->provider
            ->expects($this->never())
            ->method('send');

        $this->expectException(DeliveryException::class);

        $this->service->deliver($notification);
    }

    /** @test */
    public function it_records_failure_in_circuit_breaker_on_provider_error()
    {
        $notification = Notification::factory()->email()->create();

        // Mock circuit breaker - allow
        $this->circuitBreaker
            ->expects($this->once())
            ->method('isAvailable')
            ->with(Channel::EMAIL)
            ->willReturn(true);

        // Mock rate limiter - allow
        $this->rateLimiter
            ->expects($this->once())
            ->method('waitForSlot')
            ->with(Channel::EMAIL);

        // Mock provider - failure
        $this->provider
            ->expects($this->once())
            ->method('send')
            ->with($notification)
            ->willThrowException(DeliveryException::providerError('webhook-provider', 'Connection timeout'));

        // Should record failure in circuit breaker
        $this->circuitBreaker
            ->expects($this->once())
            ->method('recordFailure')
            ->with(Channel::EMAIL);

        // Should not record success
        $this->circuitBreaker
            ->expects($this->never())
            ->method('recordSuccess');

        $this->expectException(DeliveryException::class);

        $this->service->deliver($notification);
    }

    /** @test */
    public function it_returns_provider_name()
    {
        $this->provider
            ->expects($this->once())
            ->method('getName')
            ->willReturn('webhook-provider');

        $name = $this->service->getProviderName();

        $this->assertEquals('webhook-provider', $name);
    }

    /** @test */
    public function it_returns_circuit_breaker_stats()
    {
        $expectedStats = [
            'state' => 'closed',
            'failures' => 0,
            'is_available' => true,
        ];

        $this->circuitBreaker
            ->expects($this->once())
            ->method('getStats')
            ->with(Channel::EMAIL)
            ->willReturn($expectedStats);

        $stats = $this->service->getCircuitBreakerStats(Channel::EMAIL);

        $this->assertEquals($expectedStats, $stats);
    }

    /** @test */
    public function it_processes_different_channels_independently()
    {
        $emailNotification = Notification::factory()->email()->create();
        $smsNotification = Notification::factory()->sms()->create();

        // Mock circuit breaker - both allow
        $this->circuitBreaker
            ->expects($this->exactly(2))
            ->method('isAvailable')
            ->willReturnCallback(function ($channel) {
                return true;
            });

        // Mock rate limiter - both allow
        $this->rateLimiter
            ->expects($this->exactly(2))
            ->method('waitForSlot')
            ->willReturnCallback(function ($channel) {
                // Different channels should be called with different params
                return null;
            });

        // Mock provider - both success
        $this->provider
            ->expects($this->exactly(2))
            ->method('send')
            ->willReturnCallback(function ($notification) {
                return ['message_id' => 'msg-' . $notification->id];
            });

        // Both should record success
        $this->circuitBreaker
            ->expects($this->exactly(2))
            ->method('recordSuccess');

        $result1 = $this->service->deliver($emailNotification);
        $result2 = $this->service->deliver($smsNotification);

        $this->assertNotEmpty($result1);
        $this->assertNotEmpty($result2);
    }
}
