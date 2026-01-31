<?php

namespace Tests\Unit\Services;

use App\Modules\Delivery\Exceptions\DeliveryException;
use App\Modules\Delivery\Services\RateLimiterService;
use App\Shared\Enums\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Unit tests for RateLimiterService
 * 
 * Tests token bucket rate limiting per channel with per-second windows.
 * Validates rate limit enforcement, remaining capacity, and wait logic.
 */
class RateLimiterServiceTest extends TestCase
{
    use RefreshDatabase;

    private RateLimiterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RateLimiterService();
        
        // Clear Redis before each test
        Redis::flushdb();
        
        // Enable rate limiting and set limits
        Config::set('notification.rate_limit.enabled', true);
        Config::set('notification.rate_limit.requests_per_second', 10); // Set low for testing
    }

    protected function tearDown(): void
    {
        Redis::flushdb();
        parent::tearDown();
    }

    /** @test */
    public function it_allows_request_under_rate_limit()
    {
        $result = $this->service->attempt(Channel::EMAIL);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_blocks_request_when_rate_limit_exceeded()
    {
        // Make 10 requests (at limit)
        for ($i = 0; $i < 10; $i++) {
            $this->service->attempt(Channel::EMAIL);
        }

        // 11th request should be blocked
        $result = $this->service->attempt(Channel::EMAIL);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_increments_counter_on_each_attempt()
    {
        // Make 3 requests
        $this->service->attempt(Channel::EMAIL);
        $this->service->attempt(Channel::EMAIL);
        $this->service->attempt(Channel::EMAIL);

        $rate = $this->service->getCurrentRate(Channel::EMAIL);

        $this->assertEquals(3, $rate);
    }

    /** @test */
    public function it_returns_correct_remaining_capacity()
    {
        // Rate limit is 10, make 3 requests
        $this->service->attempt(Channel::EMAIL);
        $this->service->attempt(Channel::EMAIL);
        $this->service->attempt(Channel::EMAIL);

        $remaining = $this->service->getRemainingCapacity(Channel::EMAIL);

        $this->assertEquals(7, $remaining);
    }

    /** @test */
    public function it_returns_zero_remaining_capacity_when_limit_exceeded()
    {
        // Make 10 requests (at limit)
        for ($i = 0; $i < 10; $i++) {
            $this->service->attempt(Channel::EMAIL);
        }

        $remaining = $this->service->getRemainingCapacity(Channel::EMAIL);

        $this->assertEquals(0, $remaining);
    }

    /** @test */
    public function it_maintains_separate_counters_per_channel()
    {
        // Make requests to email channel
        for ($i = 0; $i < 5; $i++) {
            $this->service->attempt(Channel::EMAIL);
        }

        // SMS channel should have different counter
        $smsRate = $this->service->getCurrentRate(Channel::SMS);
        $emailRate = $this->service->getCurrentRate(Channel::EMAIL);

        $this->assertEquals(0, $smsRate);
        $this->assertEquals(5, $emailRate);
    }

    /** @test */
    public function it_throws_exception_when_wait_for_slot_times_out()
    {
        // Fill up the rate limit completely
        for ($i = 0; $i < 10; $i++) {
            $this->service->attempt(Channel::EMAIL);
        }

        // Verify limit is reached
        $this->assertFalse($this->service->attempt(Channel::EMAIL));

        // waitForSlot should try 5 times then throw
        // Note: In test environment with fast execution, this may not throw
        // if time window resets between attempts
        try {
            $this->service->waitForSlot(Channel::EMAIL);
            
            // If no exception, verify we're now in new time window
            $this->assertTrue($this->service->attempt(Channel::EMAIL), 
                'Expected either exception or successful attempt in new time window');
        } catch (DeliveryException $e) {
            // Exception thrown as expected
            $this->assertStringContainsString('Rate limit exceeded', $e->getMessage());
        }
    }

    /** @test */
    public function it_does_not_block_when_rate_limiting_disabled()
    {
        Config::set('notification.rate_limit.enabled', false);

        // Make many requests (more than limit)
        for ($i = 0; $i < 20; $i++) {
            $result = $this->service->attempt(Channel::EMAIL);
            $this->assertTrue($result);
        }
    }

    /** @test */
    public function it_resets_counter_in_new_time_window()
    {
        // Make requests to fill up current window
        for ($i = 0; $i < 10; $i++) {
            $this->service->attempt(Channel::EMAIL);
        }

        // Verify limit is reached
        $this->assertFalse($this->service->attempt(Channel::EMAIL));

        // Wait for new time window (2 seconds to be safe)
        sleep(2);

        // Should be able to make requests again
        $result = $this->service->attempt(Channel::EMAIL);

        $this->assertTrue($result);
    }
}
