<?php

namespace App\Http\Controllers\Api;

use App\Enums\Channel;
use App\Enums\Priority;
use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNotificationRequest;
use App\Http\Resources\NotificationCollection;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
        if ($request->isBatch()) {
            // Batch creation
            $notifications = $this->notificationService->createBatch(
                $request->input('notifications')
            );

            return response()->json([
                'message' => 'Batch notifications created successfully',
                'batch_id' => $notifications[0]->batch_id,
                'count' => count($notifications),
                'data' => NotificationResource::collection($notifications),
            ], Response::HTTP_CREATED);
        }

        // Single notification creation
        $notification = $this->notificationService->create($request->validated());

        return response()->json([
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
            return response()->json([
                'message' => 'Notification not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
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
            return response()->json([
                'message' => 'Notification not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $cancelled = $this->notificationService->cancel($notification);

        if (!$cancelled) {
            return response()->json([
                'message' => 'Cannot cancel notification in current status',
                'current_status' => $notification->status->value,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'Notification cancelled successfully',
        ]);
    }

    /**
     * Get notification statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $batchId = $request->query('batch_id');
        $stats = $this->notificationService->getStats($batchId);

        return response()->json([
            'data' => $stats,
        ]);
    }
}
