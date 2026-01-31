<?php

namespace Tests\Unit\Services;

use App\Modules\Delivery\Services\CircuitBreakerService;
use App\Shared\Enums\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Unit tests for CircuitBreakerService
 * 
 * Tests circuit breaker state transitions (closed -> open -> half-open -> closed).
 * Validates failure threshold, recovery time, and state persistence in Redis.
 */
class CircuitBreakerServiceTest extends TestCase
{
    use RefreshDatabase;

    private CircuitBreakerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CircuitBreakerService();
        
        // Clear Redis before each test
        Redis::flushdb();
        
        // Enable circuit breaker in config
        Config::set('notification.circuit_breaker.enabled', true);
        Config::set('notification.circuit_breaker.failure_threshold', 5);
        Config::set('notification.circuit_breaker.recovery_time', 60);
    }

    protected function tearDown(): void
    {
        Redis::flushdb();
        parent::tearDown();
    }

    /** @test */
    public function it_is_available_when_circuit_is_closed()
    {
        $result = $this->service->isAvailable(Channel::EMAIL);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_opens_circuit_after_threshold_failures()
    {
        // Record 5 failures (threshold)
        for ($i = 0; $i < 5; $i++) {
            $this->service->recordFailure(Channel::EMAIL);
        }

        // Circuit should now be open
        $result = $this->service->isAvailable(Channel::EMAIL);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_does_not_open_circuit_below_threshold()
    {
        // Record 4 failures (below threshold of 5)
        for ($i = 0; $i < 4; $i++) {
            $this->service->recordFailure(Channel::EMAIL);
        }

        // Circuit should still be closed
        $result = $this->service->isAvailable(Channel::EMAIL);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_resets_failures_on_success()
    {
        // Record some failures
        $this->service->recordFailure(Channel::EMAIL);
        $this->service->recordFailure(Channel::EMAIL);

        // Record success
        $this->service->recordSuccess(Channel::EMAIL);

        // Record more failures (should need 5 from scratch)
        $this->service->recordFailure(Channel::EMAIL);
        $this->service->recordFailure(Channel::EMAIL);

        // Should still be closed (only 2 failures after reset)
        $result = $this->service->isAvailable(Channel::EMAIL);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_transitions_to_half_open_after_recovery_time()
    {
        // Open circuit
        for ($i = 0; $i < 5; $i++) {
            $this->service->recordFailure(Channel::EMAIL);
        }

        // Manually set opened_at to past recovery time
        $openedAtKey = "circuit_breaker:opened_at:" . Channel::EMAIL->value;
        Redis::set($openedAtKey, now()->subSeconds(61)->timestamp);

        // Should transition to half-open
        $result = $this->service->isAvailable(Channel::EMAIL);

        $this->assertTrue($result); // Half-open allows test request
    }

    /** @test */
    public function it_closes_circuit_on_success_in_half_open_state()
    {
        // Open circuit
        for ($i = 0; $i < 5; $i++) {
            $this->service->recordFailure(Channel::EMAIL);
        }

        // Transition to half-open
        $openedAtKey = "circuit_breaker:opened_at:" . Channel::EMAIL->value;
        Redis::set($openedAtKey, now()->subSeconds(61)->timestamp);
        $this->service->isAvailable(Channel::EMAIL); // Triggers half-open

        // Record success in half-open state
        $this->service->recordSuccess(Channel::EMAIL);

        // Circuit should be closed now
        $stateKey = "circuit_breaker:state:" . Channel::EMAIL->value;
        $state = Redis::get($stateKey);

        $this->assertNull($state); // Closed state is represented by null/deleted key
    }

    /** @test */
    public function it_maintains_separate_circuits_per_channel()
    {
        // Open email circuit
        for ($i = 0; $i < 5; $i++) {
            $this->service->recordFailure(Channel::EMAIL);
        }

        // SMS circuit should still be closed
        $result = $this->service->isAvailable(Channel::SMS);

        $this->assertTrue($result);
        
        // Email circuit should be open
        $emailResult = $this->service->isAvailable(Channel::EMAIL);
        $this->assertFalse($emailResult);
    }

    /** @test */
    public function it_returns_stats_for_closed_circuit()
    {
        $stats = $this->service->getStats(Channel::EMAIL);

        $this->assertEquals('closed', $stats['state']);
        $this->assertEquals(0, $stats['failures']);
        $this->assertTrue($stats['is_available']);
    }

    /** @test */
    public function it_returns_stats_for_open_circuit()
    {
        // Open circuit
        for ($i = 0; $i < 5; $i++) {
            $this->service->recordFailure(Channel::EMAIL);
        }

        $stats = $this->service->getStats(Channel::EMAIL);

        $this->assertEquals('open', $stats['state']);
        $this->assertEquals(5, $stats['failures']);
        $this->assertFalse($stats['is_available']);
        $this->assertNotNull($stats['opened_at']);
    }

    /** @test */
    public function it_does_not_open_circuit_when_disabled_in_config()
    {
        Config::set('notification.circuit_breaker.enabled', false);

        // Record many failures
        for ($i = 0; $i < 10; $i++) {
            $this->service->recordFailure(Channel::EMAIL);
        }

        // Should still be available (circuit breaker disabled)
        $result = $this->service->isAvailable(Channel::EMAIL);

        $this->assertTrue($result);
    }
}
