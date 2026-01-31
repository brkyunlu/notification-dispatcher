<?php

use App\Modules\Observability\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Dashboard route
Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');

// Public health check (no auth, no /api prefix) – matches Swagger path /health
Route::get('/health', [HealthController::class, 'index']);
