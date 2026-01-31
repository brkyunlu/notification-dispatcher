<?php

namespace App\Modules\Notification\Jobs;

use App\Modules\Notification\Models\Notification;
use App\Shared\Enums\Priority;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job to process scheduled notifications that are due
 * 
 * This job runs periodically via the scheduler to find notifications
 * with scheduled_at <= now() and dispatch them to the queue.
 */
class ProcessScheduledNotificationsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Number of notifications to process per batch
     */
    private int $batchSize;

    /**
     * Create a new job instance.
     */
    public function __construct(int $batchSize = 100)
    {
        $this->batchSize = $batchSize;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $processedCount = 0;
        
        // Get scheduled notifications that are ready to be sent
        // Uses the scopeScheduledReady from Notification model
        $notifications = Notification::scheduledReady()
            ->orderBy('scheduled_at', 'asc')
            ->orderBy('priority', 'desc') // HIGH priority first within same scheduled time
            ->limit($this->batchSize)
            ->get();

        if ($notifications->isEmpty()) {
            Log::debug('ProcessScheduledNotificationsJob: No scheduled notifications ready');
            return;
        }

        Log::info('ProcessScheduledNotificationsJob: Processing scheduled notifications', [
            'count' => $notifications->count(),
            'batch_size' => $this->batchSize,
        ]);

        foreach ($notifications as $notification) {
            $this->dispatchNotification($notification);
            $processedCount++;
        }

        Log::info('ProcessScheduledNotificationsJob: Completed', [
            'processed' => $processedCount,
        ]);
    }

    /**
     * Dispatch a single notification to the queue
     */
    private function dispatchNotification(Notification $notification): void
    {
        try {
            // Map priority to RabbitMQ priority value (same logic as NotificationService)
            $rabbitmqPriority = $this->mapPriorityToRabbitMQ($notification->priority);
            
            // Dispatch to RabbitMQ queue
            ProcessNotificationJob::dispatch($notification, $rabbitmqPriority)
                ->onQueue('notifications')
                ->onConnection('rabbitmq');
            
            // Mark as queued
            $notification->markAsQueued();
            
            Log::debug('Scheduled notification dispatched', [
                'notification_id' => $notification->id,
                'channel' => $notification->channel->value,
                'priority' => $notification->priority->value,
                'scheduled_at' => $notification->scheduled_at->toIso8601String(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to dispatch scheduled notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Map Laravel Priority enum to RabbitMQ priority (0-255)
     * Higher value = higher priority in RabbitMQ
     */
    private function mapPriorityToRabbitMQ(Priority $priority): int
    {
        return match ($priority) {
            Priority::HIGH => 250,
            Priority::NORMAL => 100,
            Priority::LOW => 10,
        };
    }
}
