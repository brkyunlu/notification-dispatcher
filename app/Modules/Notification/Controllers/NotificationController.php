<?php

namespace App\Modules\Notification\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Exceptions\NotificationException;
use App\Modules\Notification\Requests\StoreNotificationRequest;
use App\Modules\Notification\Resources\NotificationCollection;
use App\Modules\Notification\Resources\NotificationResource;
use App\Modules\Notification\Services\NotificationService;
use App\Shared\Enums\Channel;
use App\Shared\Enums\Priority;
use App\Shared\Enums\Status;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * List notifications with filters
     */
    public function index(Request $request): NotificationCollection
    {
        // No try-catch needed - global handler catches exceptions
        $filters = $request->only([
            'status',
            'channel',
            'priority',
            'batch_id',
            'from',
            'to',
            'sort_by',
            'sort_order',
            'per_page',
        ]);

        $notifications = $this->notificationService->list($filters);

        return new NotificationCollection($notifications);
    }

    /**
     * Create single or batch notifications
     */
    public function store(StoreNotificationRequest $request): JsonResponse
    {
        // No try-catch needed - NotificationException is caught by global handler
        if ($request->isBatch()) {
            // Batch creation
            $notifications = $this->notificationService->createBatch(
                $request->input('notifications')
            );

            return response()->json([
                'success' => true,
                'message' => 'Batch notifications created successfully',
                'data' => [
                    'batch_id' => $notifications[0]->batch_id,
                    'count' => count($notifications),
                    'notifications' => NotificationResource::collection($notifications),
                ],
            ], Response::HTTP_CREATED);
        }

        // Single notification creation
        $notification = $this->notificationService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Notification created successfully',
            'data' => new NotificationResource($notification),
        ], Response::HTTP_CREATED);
    }

    /**
     * Get notification by ID
     */
    public function show(string $id): JsonResponse
    {
        $notification = $this->notificationService->find($id);

        if (!$notification) {
            throw NotificationException::notFound($id);
        }

        return response()->json([
            'success' => true,
            'data' => new NotificationResource($notification),
        ]);
    }

    /**
     * Cancel a notification
     */
    public function destroy(string $id): JsonResponse
    {
        $notification = $this->notificationService->find($id);

        if (!$notification) {
            throw NotificationException::notFound($id);
        }

        $cancelled = $this->notificationService->cancel($notification);

        if (!$cancelled) {
            throw NotificationException::cannotCancel(
                $notification->status->value,
                ['pending', 'queued']
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification cancelled successfully',
        ]);
    }

    /**
     * Get notification statistics
     */
    public function stats(Request $request): JsonResponse
    {
        // No try-catch needed - global handler catches exceptions
        $batchId = $request->query('batch_id');

        // Validate batch_id only if the client provided the parameter.
        // If batch_id is missing, we return global stats.
        if ($request->query->has('batch_id')) {
            if (!is_string($batchId) || trim($batchId) === '') {
                throw ValidationException::withMessages([
                    'batch_id' => 'The batch_id field cannot be empty.',
                ]);
            }

            if (!Str::isUuid($batchId)) {
                throw ValidationException::withMessages([
                    'batch_id' => 'The batch_id must be a valid UUID.',
                ]);
            }
        }

        $stats = $this->notificationService->getStats($batchId);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
