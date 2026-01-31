<?php

namespace App\Modules\Notification\Services;

use App\Modules\Notification\Exceptions\NotificationException;
use App\Modules\Notification\Jobs\ProcessNotificationJob;
use App\Modules\Notification\Models\Notification;
use App\Modules\Observability\Services\TracingService;
use App\Modules\Template\Models\Template;
use App\Shared\Enums\Priority;
use App\Shared\Enums\Status;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NotificationService
{
    public function __construct(
        private ?TracingService $tracingService = null
    ) {}

    /**
     * Create a single notification
     */
    public function create(array $data): Notification
    {
        [$span, $scope] = $this->tracingService?->startSpan('NotificationService.create', [
            'notification.channel' => $data['channel'] ?? 'unknown',
            'notification.priority' => $data['priority'] ?? 'normal',
        ]) ?? [null, null];

        try {
            // Generate idempotency key if provided in header
            if (request()->header('X-Idempotency-Key')) {
                $data['idempotency_key'] = request()->header('X-Idempotency-Key');
                
                // Check if idempotency key already exists
                $existing = Notification::where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    throw NotificationException::duplicateIdempotency($data['idempotency_key']);
                }
            }

            // Apply template if provided
            if (isset($data['template_id'])) {
                $data = $this->applyTemplate($data);
            }

            // Ensure status is set
            if (!isset($data['status'])) {
                $data['status'] = Status::PENDING;
            }

            // Ensure priority is set
            if (!isset($data['priority'])) {
                $data['priority'] = Priority::NORMAL;
            }

            $notification = Notification::create($data);
            
            // Add notification ID to span
            $this->tracingService?->addAttribute('notification.id', $notification->id, $span);
            
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
            $this->tracingService?->recordException($e, $span);
            if (str_contains($e->getMessage(), 'idempotency_key')) {
                throw NotificationException::duplicateIdempotency($data['idempotency_key'] ?? 'unknown');
            }
            throw $e; // Let global handler catch other unique constraint violations
        } catch (\Throwable $e) {
            $this->tracingService?->recordException($e, $span);
            throw $e;
        } finally {
            $this->tracingService?->endSpan($span, $scope);
        }
    }

    /**
     * Apply template to notification data
     */
    private function applyTemplate(array $data): array
    {
        $template = Template::find($data['template_id']);

        if (!$template) {
            throw NotificationException::templateNotFound();
        }

        if (!$template->is_active) {
            throw NotificationException::templateInactive();
        }

        // Get variables from request
        $variables = $data['variables'] ?? [];

        // Render template
        $rendered = $template->render($variables);

        // Override content and subject from template
        $data['content'] = $rendered['content'];
        if ($rendered['subject']) {
            $data['subject'] = $rendered['subject'];
        }

        // Set channel from template if not provided
        if (!isset($data['channel'])) {
            $data['channel'] = $template->channel->value;
        }

        // Validate channel matches template
        if (isset($data['channel']) && $data['channel'] !== $template->channel->value) {
            throw NotificationException::channelMismatch($template->channel->value);
        }

        return $data;
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

        // RabbitMQ native priority (0-255, higher = more priority)
        $rabbitmqPriority = $this->mapPriorityToRabbitMQ($notification->priority);
        
        // Dispatch with RabbitMQ priority
        ProcessNotificationJob::dispatch($notification, $rabbitmqPriority)
            ->onQueue('notifications')
            ->onConnection('rabbitmq');
            
        $notification->markAsQueued();
        
        Log::debug('Notification dispatched to RabbitMQ', [
            'notification_id' => $notification->id,
            'priority' => $notification->priority->value,
            'rabbitmq_priority' => $rabbitmqPriority,
        ]);
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

    /**
     * Create batch notifications
     */
    public function createBatch(array $notifications): array
    {
        [$span, $scope] = $this->tracingService?->startSpan('NotificationService.createBatch', [
            'notification.batch_size' => count($notifications),
        ]) ?? [null, null];

        try {
            $batchId = Str::uuid()->toString();
            $created = [];

            $this->tracingService?->addAttribute('notification.batch_id', $batchId, $span);

            foreach ($notifications as $notificationData) {
                $notificationData['batch_id'] = $batchId;
                
                // Apply template if provided
                if (isset($notificationData['template_id'])) {
                    $notificationData = $this->applyTemplate($notificationData);
                }
                
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

        } catch (\Throwable $e) {
            $this->tracingService?->recordException($e, $span);
            throw $e;
        } finally {
            $this->tracingService?->endSpan($span, $scope);
        }
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
