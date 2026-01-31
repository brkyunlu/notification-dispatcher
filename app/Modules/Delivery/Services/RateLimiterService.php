<?php

namespace App\Modules\Delivery\Services;

use App\Shared\Enums\Channel;
use Illuminate\Support\Facades\Redis;

class RateLimiterService
{
    private const WINDOW_SECONDS = 1;

    /**
     * Check if request is within rate limit using token bucket algorithm
     */
    public function attempt(Channel $channel): bool
    {
        // Check if rate limiting is enabled
        if (!config('notification.rate_limit.enabled', true)) {
            return true;
        }

        $rateLimit = config('notification.rate_limit.requests_per_second', 100);
        $key = "rate_limit:{$channel->value}";
        $now = now()->timestamp;
        $windowKey = "{$key}:{$now}";

        // Get current count in this second
        $count = Redis::get($windowKey) ?? 0;

        if ($count >= $rateLimit) {
            return false;
        }

        // Increment counter with expiry
        Redis::multi();
        Redis::incr($windowKey);
        Redis::expire($windowKey, self::WINDOW_SECONDS + 1);
        Redis::exec();

        return true;
    }

    /**
     * Wait until rate limit allows request
     */
    public function waitForSlot(Channel $channel): void
    {
        $maxAttempts = 5;
        $attempt = 0;

        while (!$this->attempt($channel) && $attempt < $maxAttempts) {
            usleep(200000); // 200ms
            $attempt++;
        }

        if ($attempt >= $maxAttempts) {
            throw new \RuntimeException(
                "Rate limit exceeded for channel {$channel->value}",
                429
            );
        }
    }

    /**
     * Get current rate for a channel
     */
    public function getCurrentRate(Channel $channel): int
    {
        $key = "rate_limit:{$channel->value}";
        $now = now()->timestamp;
        $windowKey = "{$key}:{$now}";

        return (int) (Redis::get($windowKey) ?? 0);
    }

    /**
     * Get remaining capacity for current second
     */
    public function getRemainingCapacity(Channel $channel): int
    {
        $rateLimit = config('notification.rate_limit.requests_per_second', 100);
        return max(0, $rateLimit - $this->getCurrentRate($channel));
    }
}
