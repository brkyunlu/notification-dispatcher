<?php

namespace App\Modules\Notification\Services;

use App\Modules\Notification\Jobs\ProcessNotificationJob;
use App\Modules\Notification\Models\Notification;
use App\Shared\Enums\Priority;
use App\Shared\Enums\Status;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Create a single notification
     */
    public function create(array $data): Notification
    {
        // Generate idempotency key if provided in header
        if (request()->header('X-Idempotency-Key')) {
            $data['idempotency_key'] = request()->header('X-Idempotency-Key');
            
            // Check if idempotency key already exists
            $existing = Notification::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                throw new \RuntimeException(
                    'This request has already been processed. Idempotency key: ' . $data['idempotency_key'],
                    409
                );
            }
        }

        // Ensure status is set
        if (!isset($data['status'])) {
            $data['status'] = Status::PENDING;
        }

        // Ensure priority is set
        if (!isset($data['priority'])) {
            $data['priority'] = Priority::NORMAL;
        }

        try {
            $notification = Notification::create($data);
            
            // Dispatch to queue based on priority
            $this->dispatchToQueue($notification);
            
            Log::info('Notification created and queued', [
                'notification_id' => $notification->id,
                'channel' => $notification->channel->value,
                'priority' => $notification->priority->value,
                'queue' => $notification->priority->getQueueName(),
            ]);
            
            return $notification;
            
        } catch (UniqueConstraintViolationException $e) {
            // Handle duplicate idempotency key
            if (str_contains($e->getMessage(), 'idempotency_key')) {
                throw new \RuntimeException(
                    'This request has already been processed. Please use a different idempotency key.',
                    409
                );
            }
            throw $e;
        } catch (QueryException $e) {
            // Handle other database errors
            throw new \RuntimeException(
                'Failed to create notification: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Dispatch notification to appropriate queue
     */
    private function dispatchToQueue(Notification $notification): void
    {
        // Skip if scheduled for later
        if ($notification->scheduled_at && $notification->scheduled_at->isFuture()) {
            return;
        }

        $queueName = $notification->priority->getQueueName();
        
        ProcessNotificationJob::dispatch($notification)
            ->onQueue($queueName);
            
        $notification->markAsQueued();
    }

    /**
     * Create batch notifications
     */
    public function createBatch(array $notifications): array
    {
        $batchId = Str::uuid()->toString();
        $created = [];

        foreach ($notifications as $notificationData) {
            $notificationData['batch_id'] = $batchId;
            
            // Ensure status is set
            if (!isset($notificationData['status'])) {
                $notificationData['status'] = Status::PENDING;
            }
            
            // Ensure priority is set
            if (!isset($notificationData['priority'])) {
                $notificationData['priority'] = Priority::NORMAL;
            }
            
            $notification = Notification::create($notificationData);
            
            // Dispatch to queue
            $this->dispatchToQueue($notification);
            
            $created[] = $notification;
        }

        Log::info('Batch notifications created and queued', [
            'batch_id' => $batchId,
            'count' => count($created),
        ]);

        return $created;
    }

    /**
     * Get paginated notifications with filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Notification::query();

        // Filter by status
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by channel
        if (isset($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        // Filter by priority
        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        // Filter by batch_id
        if (isset($filters['batch_id'])) {
            $query->where('batch_id', $filters['batch_id']);
        }

        // Filter by date range
        if (isset($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        // Sort
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = $filters['per_page'] ?? 15;
        return $query->paginate($perPage);
    }

    /**
     * Get notification by ID
     */
    public function find(string $id): ?Notification
    {
        return Notification::find($id);
    }

    /**
     * Cancel a notification
     */
    public function cancel(Notification $notification): bool
    {
        // Can only cancel pending or scheduled notifications
        if (!in_array($notification->status, [Status::PENDING, Status::QUEUED])) {
            return false;
        }

        $notification->markAsCancelled();
        return true;
    }

    /**
     * Get notification statistics
     */
    public function getStats(?string $batchId = null): array
    {
        $query = Notification::query();

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        return [
            'total' => $query->count(),
            'by_status' => $query->clone()
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'by_channel' => $query->clone()
                ->selectRaw('channel, count(*) as count')
                ->groupBy('channel')
                ->pluck('count', 'channel')
                ->toArray(),
            'by_priority' => $query->clone()
                ->selectRaw('priority, count(*) as count')
                ->groupBy('priority')
                ->pluck('count', 'priority')
                ->toArray(),
        ];
    }
}
