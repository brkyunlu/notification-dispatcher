<?php

namespace App\Modules\Notification\Jobs;

use App\Modules\Delivery\Services\DeliveryService;
use App\Modules\Notification\Models\Notification;
use App\Shared\Enums\Status;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries;
    public $backoff;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Notification $notification
    ) {
        // Load retry configuration from config
        $this->tries = config('notification.retry.max_attempts', 5);
        $this->backoff = config('notification.retry.backoff', [60, 300, 1800, 7200]);
    }

    /**
     * Execute the job.
     */
    public function handle(DeliveryService $deliveryService): void
    {
        // Check if notification was cancelled
        $this->notification->refresh();
        if ($this->notification->status === Status::CANCELLED) {
            Log::info('Notification cancelled, skipping processing', [
                'notification_id' => $this->notification->id,
            ]);
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

        try {
            // Deliver notification via DeliveryService
            // (handles rate limiting, circuit breaker, and provider)
            $response = $deliveryService->deliver($this->notification);

            // Mark as sent
            $externalId = $response['message_id'] ?? null;
            $this->notification->markAsSent($externalId);

            Log::info('Notification sent successfully', [
                'notification_id' => $this->notification->id,
                'external_message_id' => $externalId,
                'provider' => $deliveryService->getProviderName(),
            ]);

        } catch (\RuntimeException $e) {
            $this->notification->markAsFailed($e->getMessage());

            Log::error('Notification sending failed', [
                'notification_id' => $this->notification->id,
                'error' => $e->getMessage(),
                'attempt' => $this->notification->attempts,
                'status_code' => $e->getCode(),
            ]);

            // Re-throw if can retry
            if ($this->notification->canRetry()) {
                throw $e;
            }

            // Move to dead letter queue
            Log::warning('Notification moved to dead letter queue', [
                'notification_id' => $this->notification->id,
                'attempts' => $this->notification->attempts,
            ]);
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

