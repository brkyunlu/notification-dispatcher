<?php

namespace App\Modules\Delivery\Exceptions;

/**
 * Exception class for Delivery module errors
 */
class DeliveryException extends \RuntimeException
{
    /**
     * Create exception for circuit breaker open
     */
    public static function circuitBreakerOpen(string $channel): self
    {
        return new self(
            "Circuit breaker is open for channel {$channel}. Service temporarily unavailable.",
            503
        );
    }

    /**
     * Create exception for rate limit exceeded
     */
    public static function rateLimitExceeded(string $channel): self
    {
        return new self(
            "Rate limit exceeded for channel {$channel}. Please try again later.",
            429
        );
    }

    /**
     * Create exception for delivery failure
     */
    public static function deliveryFailed(string $reason): self
    {
        return new self(
            "Delivery failed: {$reason}",
            500
        );
    }

    /**
     * Create exception for provider error
     */
    public static function providerError(string $provider, string $message): self
    {
        return new self(
            "Provider {$provider} error: {$message}",
            502
        );
    }
}
