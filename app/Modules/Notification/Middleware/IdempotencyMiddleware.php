<?php

namespace App\Modules\Notification\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    /**
     * Idempotency key header name
     */
    private const HEADER_NAME = 'X-Idempotency-Key';

    /**
     * Redis key prefix
     */
    private const REDIS_PREFIX = 'idempotency:';

    /**
     * Default TTL for idempotency keys (24 hours)
     */
    private const DEFAULT_TTL = 86400;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to POST requests (create operations)
        if (!$request->isMethod('POST')) {
            return $next($request);
        }

        $idempotencyKey = $request->header(self::HEADER_NAME);

        // If no idempotency key provided, process normally
        if (!$idempotencyKey) {
            return $next($request);
        }

        // Validate idempotency key format (UUID or custom string, 1-64 chars)
        if (strlen($idempotencyKey) < 1 || strlen($idempotencyKey) > 64) {
            return $this->invalidKeyResponse('Idempotency key must be between 1 and 64 characters.');
        }

        $redisKey = $this->getRedisKey($idempotencyKey, $request);

        // Check if we have a cached response
        $cachedResponse = Redis::get($redisKey);

        if ($cachedResponse) {
            $cached = json_decode($cachedResponse, true);
            
            // Return cached response with indicator header
            return response()->json($cached['body'], $cached['status'])
                ->header('X-Idempotency-Replayed', 'true')
                ->header('X-Idempotency-Key', $idempotencyKey);
        }

        // Try to acquire lock (prevent race conditions)
        $lockKey = $redisKey . ':lock';
        $lockAcquired = Redis::setnx($lockKey, '1');
        
        if (!$lockAcquired) {
            // Another request is processing with the same key
            return $this->conflictResponse('Request with this idempotency key is already being processed.');
        }

        // Set lock expiration (10 seconds max processing time)
        Redis::expire($lockKey, 10);

        try {
            // Process the request
            $response = $next($request);

            // Only cache successful responses (2xx)
            if ($response->isSuccessful()) {
                $this->cacheResponse($redisKey, $response, $idempotencyKey);
            }

            // Add idempotency key to response
            $response->headers->set('X-Idempotency-Key', $idempotencyKey);

            return $response;

        } finally {
            // Release lock
            Redis::del($lockKey);
        }
    }

    /**
     * Generate Redis key for idempotency
     * Includes API key ID if authenticated to scope per client
     */
    private function getRedisKey(string $idempotencyKey, Request $request): string
    {
        $apiKeyId = $request->attributes->get('api_key_id', 'anonymous');
        $path = $request->path();
        
        return self::REDIS_PREFIX . md5("{$apiKeyId}:{$path}:{$idempotencyKey}");
    }

    /**
     * Cache the response
     */
    private function cacheResponse(string $redisKey, Response $response, string $idempotencyKey): void
    {
        $ttl = config('notification.idempotency.ttl', self::DEFAULT_TTL);

        $cacheData = [
            'body' => json_decode($response->getContent(), true),
            'status' => $response->getStatusCode(),
            'idempotency_key' => $idempotencyKey,
            'cached_at' => now()->toIso8601String(),
        ];

        Redis::setex($redisKey, $ttl, json_encode($cacheData));
    }

    /**
     * Return invalid key response
     */
    private function invalidKeyResponse(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => [
                'code' => 'INVALID_IDEMPOTENCY_KEY',
                'status' => 400,
            ],
        ], Response::HTTP_BAD_REQUEST);
    }

    /**
     * Return conflict response
     */
    private function conflictResponse(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => [
                'code' => 'CONFLICT',
                'status' => 409,
            ],
        ], Response::HTTP_CONFLICT);
    }
}
