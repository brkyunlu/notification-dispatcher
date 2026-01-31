<?php

use App\Modules\Notification\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

// API v1 routes - protected by API key authentication
Route::prefix('v1')->middleware('api.key')->group(function () {
    
    // Read operations (requires 'read' permission)
    Route::middleware('api.key:read')->group(function () {
        Route::get('/notifications/stats', [NotificationController::class, 'stats']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/{id}', [NotificationController::class, 'show']);
    });

    // Write operations (requires 'write' permission)
    Route::middleware('api.key:write')->group(function () {
        Route::post('/notifications', [NotificationController::class, 'store'])
            ->middleware('idempotency'); // Supports idempotency via X-Idempotency-Key header
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    });
});
