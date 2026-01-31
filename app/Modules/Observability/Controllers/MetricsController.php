<?php

namespace App\Modules\Observability\Controllers;

use App\Modules\Notification\Models\Notification;
use App\Modules\Observability\Exceptions\ObservabilityException;
use App\Shared\Enums\Status;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use OpenApi\Attributes as OA;

/**
 * Metrics endpoint for monitoring system health and performance
 */
class MetricsController
{
    /**
     * Get comprehensive system metrics
     *
     * Returns notifications (last 24h, by status/channel/priority, success rate, scheduled pending),
     * queue (RabbitMQ depth, priority breakdown, consumers), database, cache (Redis), rate_limiting (circuit breakers).
     * Requires API key with read permission.
     */
    #[OA\Get(path: '/api/v1/metrics', summary: 'Get system metrics', tags: ['Observability'])]
    #[OA\Response(
        response: 200,
        description: 'System metrics (notifications, queue, database, cache, rate_limiting)',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                        new OA\Property(
                            property: 'notifications',
                            type: 'object',
                            description: 'Last 24h: total, by_status, by_channel, by_priority, success_rate_percent; scheduled_pending'
                        ),
                        new OA\Property(
                            property: 'queue',
                            type: 'object',
                            description: 'connection, queue_name, messages_ready, messages_unacknowledged, messages_total, consumers, priority_breakdown (high/normal/low), status'
                        ),
                        new OA\Property(
                            property: 'database',
                            type: 'object',
                            description: 'status, connection, latency_ms, total_notifications'
                        ),
                        new OA\Property(
                            property: 'cache',
                            type: 'object',
                            description: 'status, driver, latency_ms, redis_version, connected_clients, used_memory_human'
                        ),
                        new OA\Property(
                            property: 'rate_limiting',
                            type: 'object',
                            description: 'circuit_breakers (sms/email/push: open|closed), active_rate_limit_keys'
                        ),
                    ]
                ),
            ],
            example: [
                'success' => true,
                'data' => [
                    'timestamp' => '2026-01-31T22:00:00.000000Z',
                    'notifications' => [
                        'last_24h' => [
                            'total' => 150,
                            'by_status' => ['sent' => 140, 'failed' => 8, 'queued' => 2],
                            'by_channel' => ['email' => 100, 'sms' => 50],
                            'by_priority' => ['high' => 10, 'normal' => 130, 'low' => 10],
                            'success_rate_percent' => 94.59,
                        ],
                        'scheduled_pending' => 5,
                    ],
                    'queue' => [
                        'connection' => 'rabbitmq',
                        'queue_name' => 'notifications',
                        'messages_ready' => 12,
                        'messages_unacknowledged' => 0,
                        'messages_total' => 12,
                        'consumers' => 2,
                        'priority_breakdown' => ['high' => 2, 'normal' => 8, 'low' => 2],
                        'status' => 'healthy',
                    ],
                    'database' => ['status' => 'connected', 'connection' => 'mysql', 'latency_ms' => 1.2, 'total_notifications' => 5000],
                    'cache' => ['status' => 'connected', 'driver' => 'redis', 'latency_ms' => 0.3, 'redis_version' => '7.0', 'connected_clients' => 3, 'used_memory_human' => '2.5M'],
                    'rate_limiting' => ['circuit_breakers' => ['sms' => 'closed', 'email' => 'closed', 'push' => 'closed'], 'active_rate_limit_keys' => 0],
                ],
            ]
        )
    )]
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
            // Get priority breakdown from database (queued notifications)
            // This is the most accurate source as it reflects actual pending jobs
            $priorityBreakdown = Notification::where('status', Status::QUEUED)
                ->selectRaw('priority, count(*) as count')
                ->groupBy('priority')
                ->pluck('count', 'priority')
                ->toArray();

            $totalQueued = array_sum($priorityBreakdown);

            // Get RabbitMQ stats for additional metrics (consumers, etc.)
            $rabbitmqStats = $this->getRabbitMQStats();

            return [
                'connection' => 'rabbitmq',
                'queue_name' => 'notifications',
                'messages_ready' => $totalQueued,
                'messages_unacknowledged' => $rabbitmqStats['messages_unacknowledged'] ?? 0,
                'messages_total' => $totalQueued + ($rabbitmqStats['messages_unacknowledged'] ?? 0),
                'consumers' => $rabbitmqStats['consumers'] ?? 0,
                'priority_breakdown' => [
                    'high' => $priorityBreakdown['high'] ?? 0,
                    'normal' => $priorityBreakdown['normal'] ?? 0,
                    'low' => $priorityBreakdown['low'] ?? 0,
                ],
                'status' => 'healthy',
            ];
        } catch (\Exception $e) {
            return [
                'connection' => config('queue.default'),
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get RabbitMQ stats from Management API
     */
    private function getRabbitMQStats(): array
    {
        try {
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
                    'messages_unacknowledged' => $queueData['messages_unacknowledged'] ?? 0,
                    'consumers' => $queueData['consumers'] ?? 0,
                ];
            }
        } catch (\Exception $e) {
            // Silently fail - not critical
        }

        return [
            'messages_unacknowledged' => 0,
            'consumers' => 0,
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
