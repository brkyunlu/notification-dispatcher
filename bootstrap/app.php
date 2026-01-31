<?php

use App\Http\Middleware\JsonResponseMiddleware;
use App\Modules\Auth\Exceptions\AuthException;
use App\Modules\Delivery\Exceptions\DeliveryException;
use App\Modules\Notification\Exceptions\NotificationException;
use App\Modules\Template\Exceptions\TemplateException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            JsonResponseMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // =====================================================
        // MODULE-SPECIFIC EXCEPTION HANDLERS
        // =====================================================

        // Notification Module Exceptions
        $exceptions->render(function (NotificationException $e, Request $request) {
            $response = [
                'error' => [
                    'code' => match ($e->getCode()) {
                        409 => 'DUPLICATE_REQUEST',
                        404 => 'NOTIFICATION_NOT_FOUND',
                        422 => 'INVALID_STATUS',
                        400 => 'BAD_REQUEST',
                        default => 'NOTIFICATION_ERROR',
                    },
                    'message' => $e->getMessage(),
                ],
            ];

            if (!empty($e->details)) {
                $response['error']['details'] = $e->details;
            }

            return response()->json($response, $e->getCode() ?: 400);
        });

        // Template Module Exceptions
        $exceptions->render(function (TemplateException $e, Request $request) {
            return response()->json([
                'error' => [
                    'code' => match ($e->getCode()) {
                        409 => 'DUPLICATE_TEMPLATE',
                        404 => 'TEMPLATE_NOT_FOUND',
                        default => 'TEMPLATE_ERROR',
                    },
                    'message' => $e->getMessage(),
                ],
            ], $e->getCode() ?: 400);
        });

        // Delivery Module Exceptions
        $exceptions->render(function (DeliveryException $e, Request $request) {
            return response()->json([
                'error' => [
                    'code' => match ($e->getCode()) {
                        503 => 'SERVICE_UNAVAILABLE',
                        502 => 'PROVIDER_ERROR',
                        429 => 'RATE_LIMIT_EXCEEDED',
                        default => 'DELIVERY_ERROR',
                    },
                    'message' => $e->getMessage(),
                ],
            ], $e->getCode() ?: 500);
        });

        // Auth Module Exceptions
        $exceptions->render(function (AuthException $e, Request $request) {
            return response()->json([
                'error' => [
                    'code' => match ($e->getCode()) {
                        401 => 'UNAUTHORIZED',
                        403 => 'FORBIDDEN',
                        default => 'AUTH_ERROR',
                    },
                    'message' => $e->getMessage(),
                ],
            ], $e->getCode() ?: 401);
        });

        // =====================================================
        // GLOBAL EXCEPTION HANDLERS (Fallback)
        // =====================================================

        // Validation Exceptions (Laravel default)
        $exceptions->render(function (ValidationException $e, Request $request) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $e->errors(),
                ],
            ], 422);
        });

        // Database Exceptions (QueryException)
        $exceptions->render(function (QueryException $e, Request $request) {
            // Log the actual error for debugging
            Log::error('Database error', [
                'sql_state' => $e->errorInfo[0] ?? null,
                'message' => $e->getMessage(),
            ]);

            // Handle duplicate entry (SQLSTATE 23000)
            if (isset($e->errorInfo[0]) && $e->errorInfo[0] === '23000') {
                return response()->json([
                    'error' => [
                        'code' => 'DUPLICATE_ENTRY',
                        'message' => 'A record with this information already exists.',
                    ],
                ], 409);
            }

            // Generic database error
            return response()->json([
                'error' => [
                    'code' => 'DATABASE_ERROR',
                    'message' => config('app.debug')
                        ? $e->getMessage()
                        : 'A database error occurred.',
                ],
            ], 500);
        });

        // Catch-all for unexpected exceptions
        $exceptions->render(function (Throwable $e, Request $request) {
            // Log all unhandled exceptions
            Log::error('Unhandled exception', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => config('app.debug')
                        ? $e->getMessage()
                        : 'An unexpected error occurred.',
                ],
            ], 500);
        });
    })->create();
