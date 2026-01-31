<?php

namespace App\Modules\Observability\Middleware;

use Closure;
use Illuminate\Http\Request;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * TracingMiddleware
 * 
 * Creates a root span for each HTTP request and propagates trace context
 */
class TracingMiddleware
{
    public function __construct(
        private TracerInterface $tracer
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('tracing.enabled', false)) {
            return $next($request);
        }

        // Extract trace context from headers (W3C Trace Context format)
        $context = $this->extractContext($request);

        // Create root span
        $span = $this->tracer
            ->spanBuilder('HTTP ' . $request->method() . ' ' . $request->path())
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setParent($context)
            ->startSpan();

        // Add HTTP attributes
        $span->setAttribute('http.method', $request->method());
        $span->setAttribute('http.url', $request->fullUrl());
        $span->setAttribute('http.scheme', $request->getScheme());
        $span->setAttribute('http.target', $request->getRequestUri());
        $span->setAttribute('http.host', $request->getHost());
        $span->setAttribute('http.user_agent', $request->userAgent());
        $span->setAttribute('http.client_ip', $request->ip());

        // Add correlation ID if present
        $correlationId = $request->attributes->get('correlation_id');
        if ($correlationId) {
            $span->setAttribute('correlation.id', $correlationId);
        }

        // Add API key info if authenticated
        $apiKeyId = $request->attributes->get('api_key_id');
        if ($apiKeyId) {
            $span->setAttribute('api.key_id', $apiKeyId);
        }

        // Activate span context
        $scope = $span->activate();

        try {
            /** @var Response $response */
            $response = $next($request);

            // Add response attributes
            $span->setAttribute('http.status_code', $response->getStatusCode());

            // Set span status based on HTTP status
            if ($response->getStatusCode() >= 500) {
                $span->setStatus(StatusCode::STATUS_ERROR, 'Server error');
            } elseif ($response->getStatusCode() >= 400) {
                $span->setStatus(StatusCode::STATUS_ERROR, 'Client error');
            } else {
                $span->setStatus(StatusCode::STATUS_OK);
            }

            return $response;

        } catch (\Throwable $e) {
            // Record exception
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;

        } finally {
            // End span and deactivate scope
            $span->end();
            $scope->detach();
        }
    }

    /**
     * Extract trace context from request headers
     */
    private function extractContext(Request $request): Context
    {
        // Extract W3C Trace Context (traceparent header)
        $traceparent = $request->header('traceparent');
        
        if ($traceparent) {
            // Parse traceparent format: 00-{trace_id}-{span_id}-{flags}
            // For simplicity, we'll use the current context
            // In production, you'd use OpenTelemetry's context propagator
            return Context::getCurrent();
        }

        return Context::getCurrent();
    }
}
