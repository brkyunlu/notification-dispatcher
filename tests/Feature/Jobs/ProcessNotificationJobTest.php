<?php

namespace Tests\Feature\Jobs;

use App\Modules\Delivery\Contracts\ProviderInterface;
use App\Modules\Delivery\Exceptions\DeliveryException;
use App\Modules\Delivery\Services\CircuitBreakerService;
use App\Modules\Delivery\Services\DeliveryService;
use App\Modules\Delivery\Services\RateLimiterService;
use App\Modules\Notification\Jobs\ProcessNotificationJob;
use App\Modules\Notification\Models\FailedNotification;
use App\Modules\Notification\Models\Notification;
use App\Shared\Enums\Priority;
use App\Shared\Enums\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Feature tests for ProcessNotificationJob
 * 
 * Tests queue job behavior: priority, retries, backoff, success/failure handling.
 * Validates interaction with DeliveryService and state transitions.
 */
class ProcessNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set retry config
        Config::set('notification.retry.max_attempts', 3);
        Config::set('notification.retry.backoff', [1, 3, 10]); // seconds
    }

    /** @test */
    public function it_dispatches_to_correct_queue()
    {
        Queue::fake();
        
        $notification = Notification::factory()->create();

        ProcessNotificationJob::dispatch($notification)
            ->onQueue('notifications')
            ->onConnection('rabbitmq');

        Queue::assertPushedOn('notifications', ProcessNotificationJob::class);
    }

    /** @test */
    public function it_uses_correct_priority_for_high_notifications()
    {
        $notification = Notification::factory()->high()->create();
        
        $job = new ProcessNotificationJob($notification, 250);

        $this->assertEquals(250, $job->priority);
    }

    /** @test */
    public function it_uses_correct_priority_for_normal_notifications()
    {
        $notification = Notification::factory()->normal()->create();
        
        $job = new ProcessNotificationJob($notification, 100);

        $this->assertEquals(100, $job->priority);
    }

    /** @test */
    public function it_uses_correct_priority_for_low_notifications()
    {
        $notification = Notification::factory()->low()->create();
        
        $job = new ProcessNotificationJob($notification, 10);

        $this->assertEquals(10, $job->priority);
    }

    /** @test */
    public function it_marks_notification_as_sent_on_success()
    {
        $notification = Notification::factory()->queued()->create();

        // Mock successful delivery
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('send')->willReturn(['message_id' => 'msg-123']);
        $provider->method('getName')->willReturn('test-provider');

        $circuitBreaker = $this->createMock(CircuitBreakerService::class);
        $circuitBreaker->method('isAvailable')->willReturn(true);

        $rateLimiter = $this->createMock(RateLimiterService::class);

        $deliveryService = new DeliveryService($provider, $circuitBreaker, $rateLimiter);

        $job = new ProcessNotificationJob($notification);
        $job->handle($deliveryService);

        $notification->refresh();
        $this->assertEquals(Status::SENT, $notification->status);
        $this->assertEquals('msg-123', $notification->external_message_id);
        $this->assertNotNull($notification->sent_at);
    }

    /** @test */
    public function it_marks_notification_as_failed_after_max_retries()
    {
        $notification = Notification::factory()->queued()->create();

        // Mock failing delivery
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('send')->willThrowException(
            DeliveryException::providerError('test-provider', 'Connection timeout')
        );
        $provider->method('getName')->willReturn('test-provider');

        $circuitBreaker = $this->createMock(CircuitBreakerService::class);
        $circuitBreaker->method('isAvailable')->willReturn(true);

        $rateLimiter = $this->createMock(RateLimiterService::class);

        $deliveryService = new DeliveryService($provider, $circuitBreaker, $rateLimiter);

        $job = new ProcessNotificationJob($notification);
        
        // Simulate max attempts
        for ($i = 0; $i < 3; $i++) {
            try {
                $job->handle($deliveryService);
            } catch (\Exception $e) {
                // Expected to throw
            }
        }

        // After max attempts, job's failed() method would be called by Laravel
        $job->failed(new \Exception('Max attempts reached'));

        $notification->refresh();
        $this->assertEquals(Status::FAILED, $notification->status);
        
        // Should record in failed_notifications
        $this->assertDatabaseHas('failed_notifications', [
            'notification_id' => $notification->id,
        ]);
    }

    /** @test */
    public function it_skips_processing_cancelled_notifications()
    {
        $notification = Notification::factory()->cancelled()->create();

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->never())->method('send'); // Should not attempt delivery

        $circuitBreaker = $this->createMock(CircuitBreakerService::class);
        $rateLimiter = $this->createMock(RateLimiterService::class);

        $deliveryService = new DeliveryService($provider, $circuitBreaker, $rateLimiter);

        $job = new ProcessNotificationJob($notification);
        $job->handle($deliveryService);

        // Status should remain cancelled
        $notification->refresh();
        $this->assertEquals(Status::CANCELLED, $notification->status);
    }

    /** @test */
    public function it_increments_attempts_counter()
    {
        $notification = Notification::factory()->queued()->create([
            'attempts' => 0,
        ]);

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('send')->willReturn(['message_id' => 'msg-123']);
        $provider->method('getName')->willReturn('test-provider');

        $circuitBreaker = $this->createMock(CircuitBreakerService::class);
        $circuitBreaker->method('isAvailable')->willReturn(true);

        $rateLimiter = $this->createMock(RateLimiterService::class);

        $deliveryService = new DeliveryService($provider, $circuitBreaker, $rateLimiter);

        $job = new ProcessNotificationJob($notification);
        $job->handle($deliveryService);

        $notification->refresh();
        $this->assertEquals(1, $notification->attempts);
    }

    /** @test */
    public function it_respects_circuit_breaker_open_state()
    {
        $notification = Notification::factory()->queued()->create();

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->never())->method('send'); // Should not attempt

        $circuitBreaker = $this->createMock(CircuitBreakerService::class);
        $circuitBreaker->method('isAvailable')->willReturn(false); // Circuit open

        $rateLimiter = $this->createMock(RateLimiterService::class);

        $deliveryService = new DeliveryService($provider, $circuitBreaker, $rateLimiter);

        $job = new ProcessNotificationJob($notification);

        $this->expectException(DeliveryException::class);
        
        $job->handle($deliveryService);
    }

    /** @test */
    public function it_has_correct_retry_configuration()
    {
        $notification = Notification::factory()->create();
        $job = new ProcessNotificationJob($notification);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals([1, 3, 10], $job->backoff);
    }

    /** @test */
    public function it_records_failure_details_in_failed_notifications_table()
    {
        $notification = Notification::factory()->queued()->create();

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('send')->willThrowException(
            DeliveryException::providerError('test-provider', 'API Error: 500')
        );
        $provider->method('getName')->willReturn('test-provider');

        $circuitBreaker = $this->createMock(CircuitBreakerService::class);
        $circuitBreaker->method('isAvailable')->willReturn(true);

        $rateLimiter = $this->createMock(RateLimiterService::class);

        $deliveryService = new DeliveryService($provider, $circuitBreaker, $rateLimiter);

        $job = new ProcessNotificationJob($notification);
        
        try {
            $job->handle($deliveryService);
        } catch (\Exception $e) {
            // Handle the exception by calling failed()
            $job->failed($e);
        }

        $failedNotification = FailedNotification::where('notification_id', $notification->id)->first();
        
        $this->assertNotNull($failedNotification);
        $this->assertStringContainsString('API Error: 500', $failedNotification->error_message);
    }
}
