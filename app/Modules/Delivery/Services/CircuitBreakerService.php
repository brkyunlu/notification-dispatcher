<?php

namespace App\Modules\Delivery\Services;

use App\Shared\Enums\Channel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CircuitBreakerService
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    /**
     * Check if circuit breaker allows request
     *
     * @param Channel $channel
     * @return bool
     */
    public function isAvailable(Channel $channel): bool
    {
        if (!config('notification.circuit_breaker.enabled', true)) {
            return true;
        }

        $state = $this->getState($channel);

        return match ($state) {
            self::STATE_CLOSED => true,
            self::STATE_OPEN => $this->tryHalfOpen($channel),
            self::STATE_HALF_OPEN => true,
            default => true,
        };
    }

    /**
     * Record successful request
     *
     * @param Channel $channel
     * @return void
     */
    public function recordSuccess(Channel $channel): void
    {
        $state = $this->getState($channel);

        if ($state === self::STATE_HALF_OPEN) {
            // Recovery successful, close circuit
            $this->closeCircuit($channel);
            Log::info('Circuit breaker closed', [
                'channel' => $channel->value,
            ]);
        }

        // Reset failure count
        $this->resetFailures($channel);
    }

    /**
     * Record failed request
     *
     * @param Channel $channel
     * @return void
     */
    public function recordFailure(Channel $channel): void
    {
        if (!config('notification.circuit_breaker.enabled', true)) {
            return;
        }

        $failures = $this->incrementFailures($channel);
        $threshold = config('notification.circuit_breaker.failure_threshold', 5);

        if ($failures >= $threshold) {
            $this->openCircuit($channel);
            Log::warning('Circuit breaker opened', [
                'channel' => $channel->value,
                'failures' => $failures,
                'threshold' => $threshold,
            ]);
        }
    }

    /**
     * Get current circuit state
     *
     * @param Channel $channel
     * @return string
     */
    private function getState(Channel $channel): string
    {
        $key = $this->getStateKey($channel);
        return Redis::get($key) ?? self::STATE_CLOSED;
    }

    /**
     * Try to transition from open to half-open state
     *
     * @param Channel $channel
     * @return bool
     */
    private function tryHalfOpen(Channel $channel): bool
    {
        $openedAt = $this->getOpenedAt($channel);
        if (!$openedAt) {
            return true;
        }

        $recoveryTime = config('notification.circuit_breaker.recovery_time', 30);
        $elapsedTime = now()->timestamp - $openedAt;

        if ($elapsedTime >= $recoveryTime) {
            // Try half-open state (allow one test request)
            $this->setHalfOpen($channel);
            Log::info('Circuit breaker half-open', [
                'channel' => $channel->value,
                'recovery_time' => $recoveryTime,
            ]);
            return true;
        }

        return false;
    }

    /**
     * Open circuit (block requests)
     *
     * @param Channel $channel
     * @return void
     */
    private function openCircuit(Channel $channel): void
    {
        $stateKey = $this->getStateKey($channel);
        $timeKey = $this->getOpenedAtKey($channel);
        
        Redis::set($stateKey, self::STATE_OPEN);
        Redis::set($timeKey, now()->timestamp);
        
        // Auto-expire after recovery time + buffer
        $recoveryTime = config('notification.circuit_breaker.recovery_time', 30);
        Redis::expire($stateKey, $recoveryTime + 60);
        Redis::expire($timeKey, $recoveryTime + 60);
    }

    /**
     * Set half-open state
     *
     * @param Channel $channel
     * @return void
     */
    private function setHalfOpen(Channel $channel): void
    {
        $stateKey = $this->getStateKey($channel);
        Redis::set($stateKey, self::STATE_HALF_OPEN);
        Redis::expire($stateKey, 60); // 1 minute TTL
    }

    /**
     * Close circuit (allow requests)
     *
     * @param Channel $channel
     * @return void
     */
    private function closeCircuit(Channel $channel): void
    {
        $stateKey = $this->getStateKey($channel);
        $timeKey = $this->getOpenedAtKey($channel);
        
        Redis::del($stateKey);
        Redis::del($timeKey);
    }

    /**
     * Increment failure count
     *
     * @param Channel $channel
     * @return int
     */
    private function incrementFailures(Channel $channel): int
    {
        $key = $this->getFailureCountKey($channel);
        $failures = Redis::incr($key);
        
        // Expire after recovery time
        $recoveryTime = config('notification.circuit_breaker.recovery_time', 30);
        Redis::expire($key, $recoveryTime);
        
        return $failures;
    }

    /**
     * Reset failure count
     *
     * @param Channel $channel
     * @return void
     */
    private function resetFailures(Channel $channel): void
    {
        $key = $this->getFailureCountKey($channel);
        Redis::del($key);
    }

    /**
     * Get opened timestamp
     *
     * @param Channel $channel
     * @return int|null
     */
    private function getOpenedAt(Channel $channel): ?int
    {
        $key = $this->getOpenedAtKey($channel);
        $timestamp = Redis::get($key);
        return $timestamp ? (int) $timestamp : null;
    }

    /**
     * Get circuit state Redis key
     *
     * @param Channel $channel
     * @return string
     */
    private function getStateKey(Channel $channel): string
    {
        return "circuit_breaker:state:{$channel->value}";
    }

    /**
     * Get opened timestamp Redis key
     *
     * @param Channel $channel
     * @return string
     */
    private function getOpenedAtKey(Channel $channel): string
    {
        return "circuit_breaker:opened_at:{$channel->value}";
    }

    /**
     * Get failure count Redis key
     *
     * @param Channel $channel
     * @return string
     */
    private function getFailureCountKey(Channel $channel): string
    {
        return "circuit_breaker:failures:{$channel->value}";
    }

    /**
     * Get circuit breaker statistics
     *
     * @param Channel $channel
     * @return array
     */
    public function getStats(Channel $channel): array
    {
        $state = $this->getState($channel);
        $failures = Redis::get($this->getFailureCountKey($channel)) ?? 0;
        $openedAt = $this->getOpenedAt($channel);

        return [
            'state' => $state,
            'failures' => (int) $failures,
            'opened_at' => $openedAt,
            'is_available' => $this->isAvailable($channel),
        ];
    }
}
