<?php

namespace App\Services;

use App\Enums\Status;
use App\Models\Notification;
use Illuminate\Support\Str;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Database\QueryException;

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

        try {
            return Notification::create($data);
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
     * Create batch notifications
     */
    public function createBatch(array $notifications): array
    {
        $batchId = Str::uuid()->toString();
        $created = [];

        foreach ($notifications as $notificationData) {
            $notificationData['batch_id'] = $batchId;
            $created[] = Notification::create($notificationData);
        }

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
