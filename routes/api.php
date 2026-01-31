<?php

use App\Modules\Notification\Controllers\NotificationController;
use App\Modules\Template\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

// API v1 routes - protected by API key authentication
Route::prefix('v1')->middleware('api.key')->group(function () {
    
    // Read operations (requires 'read' permission)
    Route::middleware('api.key:read')->group(function () {
        // Notifications
        Route::get('/notifications/stats', [NotificationController::class, 'stats']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/{id}', [NotificationController::class, 'show']);
        
        // Templates
        Route::get('/templates', [TemplateController::class, 'index']);
        Route::get('/templates/{id}', [TemplateController::class, 'show']);
        Route::post('/templates/{id}/render', [TemplateController::class, 'render']);
    });

    // Write operations (requires 'write' permission)
    Route::middleware('api.key:write')->group(function () {
        // Notifications
        Route::post('/notifications', [NotificationController::class, 'store'])
            ->middleware('idempotency'); // Supports idempotency via X-Idempotency-Key header
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
        
        // Templates
        Route::post('/templates', [TemplateController::class, 'store']);
        Route::put('/templates/{id}', [TemplateController::class, 'update']);
        Route::delete('/templates/{id}', [TemplateController::class, 'destroy']);
    });
});
