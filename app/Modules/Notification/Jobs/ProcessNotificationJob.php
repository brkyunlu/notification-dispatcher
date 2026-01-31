<?php

namespace App\Modules\Notification\Jobs;

use App\Modules\Delivery\Services\DeliveryService;
use App\Modules\Notification\Models\Notification;
use App\Modules\Observability\Services\TracingService;
use App\Shared\Enums\Status;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use OpenTelemetry\API\Trace\SpanKind;

class ProcessNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries;
    public $backoff;

    /**
     * RabbitMQ priority (0-255, higher = more priority)
     * Used by laravel-queue-rabbitmq package
     */
    public int $priority = 100;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Notification $notification,
        ?int $rabbitmqPriority = null
    ) {
        // Load retry configuration from config
        $this->tries = config('notification.retry.max_attempts', 5);
        $this->backoff = config('notification.retry.backoff', [60, 300, 1800, 7200]);
        
        // Set RabbitMQ priority if provided
        if ($rabbitmqPriority !== null) {
            $this->priority = $rabbitmqPriority;
        }
    }

    /**
     * Execute the job.
     */
    public function handle(DeliveryService $deliveryService, ?TracingService $tracingService = null): void
    {
        [$span, $scope] = $tracingService?->startSpan('ProcessNotificationJob.handle', [
            'notification.id' => $this->notification->id,
            'notification.channel' => $this->notification->channel->value,
            'notification.priority' => $this->notification->priority->value,
            'notification.attempt' => $this->notification->attempts + 1,
        ], SpanKind::KIND_CONSUMER) ?? [null, null];

        try {
            // Check if notification was cancelled
            $this->notification->refresh();
            if ($this->notification->status === Status::CANCELLED) {
                Log::info('Notification cancelled, skipping processing', [
                    'notification_id' => $this->notification->id,
                ]);
                $tracingService?->addAttribute('notification.cancelled', true, $span);
                return;
            }

            // Mark as processing
            $this->notification->markAsProcessing();
            $this->notification->incrementAttempts();

            Log::info('Processing notification', [
                'notification_id' => $this->notification->id,
                'channel' => $this->notification->channel->value,
                'priority' => $this->notification->priority->value,
                'attempt' => $this->notification->attempts,
                'provider' => $deliveryService->getProviderName(),
            ]);

            // Deliver notification via DeliveryService
            // (handles rate limiting, circuit breaker, and provider)
            $response = $deliveryService->deliver($this->notification);

            // Mark as sent
            $externalId = $response['message_id'] ?? null;
            $this->notification->markAsSent($externalId);

            $tracingService?->addAttribute('notification.external_message_id', $externalId, $span);
            $tracingService?->addAttribute('notification.status', 'sent', $span);

            Log::info('Notification sent successfully', [
                'notification_id' => $this->notification->id,
                'external_message_id' => $externalId,
                'provider' => $deliveryService->getProviderName(),
            ]);

        } catch (\RuntimeException $e) {
            $tracingService?->recordException($e, $span);
            
            // Store error temporarily but don't mark as FAILED yet (allows retry)
            $this->notification->update(['last_error' => $e->getMessage()]);

            Log::error('Notification sending failed', [
                'notification_id' => $this->notification->id,
                'error' => $e->getMessage(),
                'attempt' => $this->notification->attempts,
                'status_code' => $e->getCode(),
            ]);

            // Re-throw if can retry
            if ($this->notification->canRetry()) {
                Log::info('Notification will be retried', [
                    'notification_id' => $this->notification->id,
                    'attempt' => $this->notification->attempts,
                    'max_attempts' => config('notification.retry.max_attempts', 5),
                ]);
                throw $e;
            }

            // Retry limit reached - mark as permanently failed
            $this->notification->markAsFailed($e->getMessage());
            
            $tracingService?->addAttribute('notification.dead_letter', true, $span);
            Log::warning('Notification moved to dead letter queue', [
                'notification_id' => $this->notification->id,
                'attempts' => $this->notification->attempts,
            ]);
        } finally {
            $tracingService?->endSpan($span, $scope);
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        $this->notification->refresh();
        $this->notification->markAsFailed($exception->getMessage());

        // Create failed notification record (Dead Letter Queue)
        $this->notification->failures()->create([
            'channel' => $this->notification->channel,
            'error_message' => $exception->getMessage(),
            'http_status_code' => $exception->getCode(),
            'error_context' => [
                'attempts' => $this->notification->attempts,
                'last_attempt_at' => now()->toIso8601String(),
            ],
            'failed_at' => now(),
        ]);

        Log::error('Notification permanently failed', [
            'notification_id' => $this->notification->id,
            'error' => $exception->getMessage(),
            'total_attempts' => $this->notification->attempts,
        ]);
    }
}

