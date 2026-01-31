<?php

namespace App\Modules\Observability\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global API rate limiting middleware for DDoS protection
 * 
 * Limits:
 * - Authenticated (API key): 1000 requests/minute
 * - Unauthenticated (IP-based): 100 requests/minute
 */
class ApiRateLimitMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get identifier (API key or IP)
        $identifier = $this->getIdentifier($request);
        $isAuthenticated = $request->attributes->has('api_key');
        
        // Set limits based on authentication
        $limit = $isAuthenticated ? 1000 : 100; // requests per minute
        $key = "api_rate_limit:{$identifier}";
        $currentMinute = now()->format('Y-m-d H:i'); // Current minute window
        $windowKey = "{$key}:{$currentMinute}";
        
        // Get current count in this minute
        $currentCount = (int) Redis::get($windowKey);
        
        if ($currentCount >= $limit) {
            // Calculate reset time (next minute)
            $resetAt = now()->addMinute()->startOfMinute()->timestamp;
            $retryAfter = $resetAt - now()->timestamp;
            
            return response()->json([
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Too many requests. Please slow down.',
                ],
            ], Response::HTTP_TOO_MANY_REQUESTS)
                ->withHeaders([
                    'X-RateLimit-Limit' => $limit,
                    'X-RateLimit-Remaining' => 0,
                    'X-RateLimit-Reset' => $resetAt,
                    'Retry-After' => $retryAfter,
                ]);
        }
        
        // Increment counter with 70-second expiry (minute + buffer)
        Redis::multi();
        Redis::incr($windowKey);
        Redis::expire($windowKey, 70);
        Redis::exec();
        
        /** @var Response $response */
        $response = $next($request);
        
        // Add rate limit headers to response
        $remaining = max(0, $limit - $currentCount - 1);
        $resetAt = now()->addMinute()->startOfMinute()->timestamp;
        
        $response->headers->set('X-RateLimit-Limit', $limit);
        $response->headers->set('X-RateLimit-Remaining', $remaining);
        $response->headers->set('X-RateLimit-Reset', $resetAt);
        
        return $response;
    }

    /**
     * Get unique identifier for rate limiting
     */
    private function getIdentifier(Request $request): string
    {
        // Use API key if authenticated
        $apiKey = $request->attributes->get('api_key');
        if ($apiKey) {
            return 'key_' . $apiKey->id;
        }
        
        // Fall back to IP address
        return 'ip_' . $request->ip();
    }
}
