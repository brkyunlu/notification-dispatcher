<?php

namespace App\Modules\Observability\Controllers;

use App\Modules\Notification\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Metrics endpoint for monitoring system health and performance
 */
class MetricsController
{
    /**
     * Get comprehensive system metrics
     */
    public function index(): JsonResponse
    {
        $metrics = [
            'timestamp' => now()->toIso8601String(),
            'notifications' => $this->getNotificationMetrics(),
            'queue' => $this->getQueueMetrics(),
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'rate_limiting' => $this->getRateLimitMetrics(),
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    /**
     * Get notification-specific metrics
     */
    private function getNotificationMetrics(): array
    {
        // Total counts by status (last 24h)
        $last24h = now()->subDay();
        
        $statusCounts = Notification::where('created_at', '>=', $last24h)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Total counts by channel (last 24h)
        $channelCounts = Notification::where('created_at', '>=', $last24h)
            ->selectRaw('channel, count(*) as count')
            ->groupBy('channel')
            ->pluck('count', 'channel')
            ->toArray();

        // Total counts by priority (last 24h)
        $priorityCounts = Notification::where('created_at', '>=', $last24h)
            ->selectRaw('priority, count(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();

        // Success rate (sent / total non-cancelled)
        $totalSent = $statusCounts['sent'] ?? 0;
        $totalFailed = $statusCounts['failed'] ?? 0;
        $totalProcessed = $totalSent + $totalFailed;
        $successRate = $totalProcessed > 0 ? round(($totalSent / $totalProcessed) * 100, 2) : 0;

        // Scheduled notifications pending
        $scheduledPending = Notification::where('status', 'pending')
            ->whereNotNull('scheduled_at')
            ->count();

        return [
            'last_24h' => [
                'total' => array_sum($statusCounts),
                'by_status' => $statusCounts,
                'by_channel' => $channelCounts,
                'by_priority' => $priorityCounts,
                'success_rate_percent' => $successRate,
            ],
            'scheduled_pending' => $scheduledPending,
        ];
    }

    /**
     * Get queue depth and processing metrics
     */
    private function getQueueMetrics(): array
    {
        try {
            // RabbitMQ Management API endpoint
            $rabbitmqHost = config('queue.connections.rabbitmq.host', 'rabbitmq');
            $rabbitmqUser = config('queue.connections.rabbitmq.user', 'notification');
            $rabbitmqPass = config('queue.connections.rabbitmq.password', 'secret');
            $rabbitmqVhost = config('queue.connections.rabbitmq.vhost', 'notifications');
            
            $url = "http://{$rabbitmqHost}:15672/api/queues/" . urlencode($rabbitmqVhost) . "/notifications";
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, "{$rabbitmqUser}:{$rabbitmqPass}");
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $queueData = json_decode($response, true);
                
                return [
                    'connection' => 'rabbitmq',
                    'queue_name' => 'notifications',
                    'messages_ready' => $queueData['messages_ready'] ?? 0,
                    'messages_unacknowledged' => $queueData['messages_unacknowledged'] ?? 0,
                    'messages_total' => $queueData['messages'] ?? 0,
                    'consumers' => $queueData['consumers'] ?? 0,
                    'status' => 'healthy',
                ];
            }
        } catch (\Exception $e) {
            // Fallback if RabbitMQ Management API is not accessible
        }

        return [
            'connection' => config('queue.default'),
            'status' => 'unknown',
            'note' => 'RabbitMQ Management API not accessible',
        ];
    }

    /**
     * Get database connection metrics
     */
    private function getDatabaseMetrics(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $latency = round((microtime(true) - $start) * 1000, 2); // ms

            $tableCount = DB::table('notifications')->count();
            
            return [
                'status' => 'connected',
                'connection' => config('database.default'),
                'latency_ms' => $latency,
                'total_notifications' => $tableCount,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed',
            ];
        }
    }

    /**
     * Get cache/Redis metrics
     */
    private function getCacheMetrics(): array
    {
        try {
            $start = microtime(true);
            Cache::get('health_check_' . time());
            $latency = round((microtime(true) - $start) * 1000, 2); // ms

            // Get Redis info
            $redis = Redis::connection();
            $info = $redis->info();
            
            return [
                'status' => 'connected',
                'driver' => config('cache.default'),
                'latency_ms' => $latency,
                'redis_version' => $info['Server']['redis_version'] ?? 'unknown',
                'connected_clients' => $info['Clients']['connected_clients'] ?? 0,
                'used_memory_human' => $info['Memory']['used_memory_human'] ?? 'unknown',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Cache connection failed',
            ];
        }
    }

    /**
     * Get rate limiting metrics (circuit breaker + API rate limits)
     */
    private function getRateLimitMetrics(): array
    {
        try {
            $redis = Redis::connection();
            
            // Circuit breaker status for channels
            $circuitBreakers = [];
            foreach (['sms', 'email', 'push'] as $channel) {
                $key = "circuit_breaker:{$channel}";
                $state = $redis->get($key);
                $circuitBreakers[$channel] = $state ? 'open' : 'closed';
            }

            // Count rate limit keys (rough estimate of active rate limits)
            $rateLimitKeys = 0;
            try {
                $keys = $redis->keys('rate_limit:*');
                $rateLimitKeys = is_array($keys) ? count($keys) : 0;
            } catch (\Exception $e) {
                // keys() might not be available in cluster mode
            }

            return [
                'circuit_breakers' => $circuitBreakers,
                'active_rate_limit_keys' => $rateLimitKeys,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Failed to fetch rate limit metrics',
            ];
        }
    }
}
