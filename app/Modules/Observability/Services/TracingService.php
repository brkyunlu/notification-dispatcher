<?php

namespace App\Modules\Observability\Services;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;

/**
 * Helper service for distributed tracing operations
 */
class TracingService
{
    public function __construct(
        private TracerInterface $tracer
    ) {}

    /**
     * Check if tracing is enabled
     */
    public function isEnabled(): bool
    {
        return config('tracing.enabled', false);
    }

    /**
     * Create and start a span
     * 
     * @param string $name Span name (e.g., "NotificationService.create")
     * @param array $attributes Additional span attributes
     * @param string $kind Span kind (internal, client, server, producer, consumer)
     * @return array [span, scope] - must call end() and detach() when done
     */
    public function startSpan(string $name, array $attributes = [], string $kind = SpanKind::KIND_INTERNAL): array
    {
        if (!$this->isEnabled()) {
            return [null, null];
        }

        $span = $this->tracer
            ->spanBuilder($name)
            ->setSpanKind($kind)
            ->startSpan();

        // Add attributes
        foreach ($attributes as $key => $value) {
            $span->setAttribute($key, $value);
        }

        // Activate context
        $scope = $span->activate();

        return [$span, $scope];
    }

    /**
     * End a span safely
     */
    public function endSpan($span, $scope): void
    {
        if ($span) {
            $span->end();
        }
        if ($scope) {
            $scope->detach();
        }
    }

    /**
     * Record an exception in the current span
     */
    public function recordException(\Throwable $e, $span = null): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        if ($span) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
        }
    }

    /**
     * Add attribute to current span
     */
    public function addAttribute(string $key, $value, $span = null): void
    {
        if (!$this->isEnabled() || !$span) {
            return;
        }

        $span->setAttribute($key, $value);
    }
}
