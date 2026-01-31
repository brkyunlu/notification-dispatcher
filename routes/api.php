<?php

use App\Modules\Notification\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

// Notification routes
Route::prefix('v1')->group(function () {
    // Notifications - stats must come before {id} route
    Route::get('/notifications/stats', [NotificationController::class, 'stats']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications', [NotificationController::class, 'store']); // Supports both single and batch
    Route::get('/notifications/{id}', [NotificationController::class, 'show']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
});
