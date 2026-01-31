<?php

namespace App\Modules\Delivery\Services;

use App\Modules\Delivery\Contracts\ProviderInterface;
use App\Modules\Delivery\Exceptions\DeliveryException;
use App\Modules\Notification\Models\Notification;
use Illuminate\Support\Facades\Log;

class DeliveryService
{
    public function __construct(
        private ProviderInterface $provider,
        private CircuitBreakerService $circuitBreaker,
        private RateLimiterService $rateLimiter,
    ) {}

    /**
     * Deliver notification via provider
     *
     * @param Notification $notification
     * @return array
     * @throws DeliveryException
     */
    public function deliver(Notification $notification): array
    {
        $channel = $notification->channel;

        // Check circuit breaker
        if (!$this->circuitBreaker->isAvailable($channel)) {
            Log::warning('Circuit breaker open, delivery blocked', [
                'notification_id' => $notification->id,
                'channel' => $channel->value,
            ]);

            throw DeliveryException::circuitBreakerOpen($channel->value);
        }

        // Apply rate limiting
        try {
            $this->rateLimiter->waitForSlot($channel);
        } catch (DeliveryException $e) {
            Log::error('Rate limit exceeded', [
                'notification_id' => $notification->id,
                'channel' => $channel->value,
            ]);
            throw $e;
        }

        // Attempt delivery
        try {
            $result = $this->provider->send($notification);

            // Record success for circuit breaker
            $this->circuitBreaker->recordSuccess($channel);

            Log::info('Notification delivered successfully', [
                'notification_id' => $notification->id,
                'channel' => $channel->value,
                'provider' => $this->provider->getName(),
            ]);

            return $result;

        } catch (DeliveryException $e) {
            // Record failure for circuit breaker
            $this->circuitBreaker->recordFailure($channel);

            Log::error('Notification delivery failed', [
                'notification_id' => $notification->id,
                'channel' => $channel->value,
                'provider' => $this->provider->getName(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get provider name
     *
     * @return string
     */
    public function getProviderName(): string
    {
        return $this->provider->getName();
    }

    /**
     * Get circuit breaker stats for channel
     *
     * @param \App\Shared\Enums\Channel $channel
     * @return array
     */
    public function getCircuitBreakerStats(\App\Shared\Enums\Channel $channel): array
    {
        return $this->circuitBreaker->getStats($channel);
    }
}
