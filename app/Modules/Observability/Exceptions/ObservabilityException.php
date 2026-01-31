<?php

namespace App\Modules\Observability\Exceptions;

/**
 * Exception class for Observability module errors
 */
class ObservabilityException extends \RuntimeException
{
    /**
     * Additional error details
     */
    public array $details = [];

    /**
     * Create exception for database health check failure
     */
    public static function databaseUnhealthy(string $reason): self
    {
        $exception = new self(
            'Database health check failed.',
            503
        );
        $exception->details = ['reason' => $reason];
        return $exception;
    }

    /**
     * Create exception for cache health check failure
     */
    public static function cacheUnhealthy(string $reason): self
    {
        $exception = new self(
            'Cache health check failed.',
            503
        );
        $exception->details = ['reason' => $reason];
        return $exception;
    }

    /**
     * Create exception for queue health check failure
     */
    public static function queueUnhealthy(string $reason): self
    {
        $exception = new self(
            'Queue health check failed.',
            503
        );
        $exception->details = ['reason' => $reason];
        return $exception;
    }

    /**
     * Create exception for metrics collection failure
     */
    public static function metricsUnavailable(string $component): self
    {
        return new self(
            "Metrics unavailable for component: {$component}",
            503
        );
    }

    /**
     * Create exception for tracing service error
     */
    public static function tracingError(string $message): self
    {
        return new self(
            "Tracing error: {$message}",
            500
        );
    }

    /**
     * Add details to the exception
     */
    public function withDetails(array $details): self
    {
        $this->details = array_merge($this->details, $details);
        return $this;
    }
}
