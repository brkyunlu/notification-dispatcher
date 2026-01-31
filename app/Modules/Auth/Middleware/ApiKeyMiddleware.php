<?php

namespace App\Modules\Auth\Middleware;

use App\Modules\Auth\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $token = $this->extractToken($request);

        if (!$token) {
            return $this->unauthorizedResponse('API key is required. Use Authorization: Bearer <api_key> header.');
        }

        $apiKey = ApiKey::where('key', $token)->first();

        if (!$apiKey) {
            return $this->unauthorizedResponse('Invalid API key.');
        }

        if (!$apiKey->isValid()) {
            if (!$apiKey->is_active) {
                return $this->unauthorizedResponse('API key has been deactivated.');
            }
            if ($apiKey->expires_at && $apiKey->expires_at->isPast()) {
                return $this->unauthorizedResponse('API key has expired.');
            }
        }

        // Check permission if specified
        if ($permission && !$apiKey->hasPermission($permission)) {
            return $this->forbiddenResponse("Insufficient permissions. Required: {$permission}");
        }

        // Update last used timestamp (async to not slow down request)
        $apiKey->markAsUsed();

        // Store API key in request for later use
        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('api_key_id', $apiKey->id);
        $request->attributes->set('api_key_name', $apiKey->name);

        return $next($request);
    }

    /**
     * Extract token from request
     */
    protected function extractToken(Request $request): ?string
    {
        // Try Authorization header first (Bearer token)
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Try X-API-Key header as fallback
        $apiKeyHeader = $request->header('X-API-Key');
        if ($apiKeyHeader) {
            return $apiKeyHeader;
        }

        // Try query parameter as last resort (not recommended for production)
        return $request->query('api_key');
    }

    /**
     * Return 401 Unauthorized response
     */
    protected function unauthorizedResponse(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'status' => 401,
            ],
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Return 403 Forbidden response
     */
    protected function forbiddenResponse(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => [
                'code' => 'FORBIDDEN',
                'status' => 403,
            ],
        ], Response::HTTP_FORBIDDEN);
    }
}
