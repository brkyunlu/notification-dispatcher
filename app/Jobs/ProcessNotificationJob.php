<?php

namespace App\Jobs;

use App\Enums\Status;
use App\Models\Notification;
use App\Services\RateLimiterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
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
    public function handle(RateLimiterService $rateLimiter): void
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
        ]);

        try {
            // Apply rate limiting
            $rateLimiter->waitForSlot($this->notification->channel);

            // Send notification via webhook
            $response = $this->sendToProvider();

            // Mark as sent
            $externalId = $response['message_id'] ?? null;
            $this->notification->markAsSent($externalId);

            Log::info('Notification sent successfully', [
                'notification_id' => $this->notification->id,
                'external_message_id' => $externalId,
            ]);

        } catch (\Exception $e) {
            $this->notification->markAsFailed($e->getMessage());

            Log::error('Notification sending failed', [
                'notification_id' => $this->notification->id,
                'error' => $e->getMessage(),
                'attempt' => $this->notification->attempts,
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
     * Send notification to provider (webhook.site)
     */
    private function sendToProvider(): array
    {
        // Use webhook.site for testing
        $webhookUrl = config('notification.webhook_url', 'https://webhook.site/unique-uuid');

        $payload = [
            'id' => $this->notification->id,
            'recipient' => $this->notification->recipient,
            'channel' => $this->notification->channel->value,
            'content' => $this->notification->content,
            'subject' => $this->notification->subject,
            'priority' => $this->notification->priority->value,
        ];

        // Get HTTP settings from config
        $timeout = config('notification.provider.timeout', 30);
        $retryAttempts = config('notification.provider.retry_attempts', 2);
        $retryDelay = config('notification.provider.retry_delay', 100);

        $response = Http::timeout($timeout)
            ->retry($retryAttempts, $retryDelay)
            ->post($webhookUrl, $payload);

        if (!$response->successful()) {
            throw new \RuntimeException(
                "Provider returned error: {$response->status()}",
                $response->status()
            );
        }

        return [
            'message_id' => $this->notification->id,
            'status' => 'sent',
        ];
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        $this->notification->refresh();
        $this->notification->markAsFailed($exception->getMessage());

        // Create failed notification record
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

