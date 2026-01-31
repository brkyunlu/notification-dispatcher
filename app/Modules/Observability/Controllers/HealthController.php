<?php

namespace App\Modules\Observability\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Health check endpoint for monitoring system dependencies
 */
class HealthController
{
    /**
     * Comprehensive health check
     */
    public function index(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
        ];

        $allHealthy = collect($checks)->every(fn($check) => $check['status'] === 'healthy');
        $httpStatus = $allHealthy ? 200 : 503;

        return response()->json([
            'status' => $allHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $httpStatus);
    }

    /**
     * Check database connectivity
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $latency = round((microtime(true) - $start) * 1000, 2);

            // Simple query test
            DB::table('notifications')->limit(1)->count();

            return [
                'status' => 'healthy',
                'latency_ms' => $latency,
                'connection' => config('database.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => 'Database connection failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Connection error',
            ];
        }
    }

    /**
     * Check cache/Redis connectivity
     */
    private function checkCache(): array
    {
        try {
            $start = microtime(true);
            $testKey = 'health_check_' . time();
            $testValue = 'test';
            
            Cache::put($testKey, $testValue, 10);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);
            
            $latency = round((microtime(true) - $start) * 1000, 2);

            if ($retrieved !== $testValue) {
                throw new \Exception('Cache write/read mismatch');
            }

            return [
                'status' => 'healthy',
                'latency_ms' => $latency,
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => 'Cache connection failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Connection error',
            ];
        }
    }

    /**
     * Check queue connectivity (RabbitMQ)
     */
    private function checkQueue(): array
    {
        try {
            // Check RabbitMQ Management API
            $rabbitmqHost = config('queue.connections.rabbitmq.host', 'rabbitmq');
            $rabbitmqUser = config('queue.connections.rabbitmq.user', 'notification');
            $rabbitmqPass = config('queue.connections.rabbitmq.password', 'secret');
            
            $url = "http://{$rabbitmqHost}:15672/api/overview";
            
            $start = microtime(true);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, "{$rabbitmqUser}:{$rabbitmqPass}");
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $latency = round((microtime(true) - $start) * 1000, 2);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                
                return [
                    'status' => 'healthy',
                    'latency_ms' => $latency,
                    'connection' => 'rabbitmq',
                    'version' => $data['rabbitmq_version'] ?? 'unknown',
                ];
            }

            throw new \Exception("HTTP {$httpCode}");
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => 'Queue connection failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Connection error',
            ];
        }
    }
}
