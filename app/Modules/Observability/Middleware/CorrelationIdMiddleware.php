<?php

namespace App\Modules\Observability\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds a unique correlation ID to each request for tracing across logs
 */
class CorrelationIdMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generate or use existing correlation ID
        $correlationId = $request->header('X-Correlation-ID') ?? Str::uuid()->toString();
        
        // Store in request for use in controllers/services
        $request->attributes->set('correlation_id', $correlationId);
        
        // Add to log context globally
        logger()->withContext([
            'correlation_id' => $correlationId,
            'request_id' => $correlationId, // alias
        ]);

        /** @var Response $response */
        $response = $next($request);

        // Add correlation ID to response headers
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
