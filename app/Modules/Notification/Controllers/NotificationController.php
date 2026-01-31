<?php

namespace App\Modules\Notification\Controllers;

use App\Http\Controllers\Controller;
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

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * List notifications with filters
     */
    public function index(Request $request): NotificationCollection|JsonResponse
    {
        try {
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
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'An error occurred while fetching notifications',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create single or batch notifications
     */
    public function store(StoreNotificationRequest $request): JsonResponse
    {
        try {
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
            
        } catch (\RuntimeException $e) {
            // Handle idempotency and business logic errors
            $statusCode = $e->getCode() ?: Response::HTTP_BAD_REQUEST;
            
            return response()->json([
                'error' => [
                    'code' => $statusCode === 409 ? 'DUPLICATE_REQUEST' : 'BAD_REQUEST',
                    'message' => $e->getMessage(),
                ],
            ], $statusCode);
            
        } catch (\Exception $e) {
            // Handle unexpected errors
            return response()->json([
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'An unexpected error occurred while processing your request',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get notification by ID
     */
    public function show(string $id): JsonResponse
    {
        $notification = $this->notificationService->find($id);

        if (!$notification) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Notification not found',
                ],
            ], Response::HTTP_NOT_FOUND);
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
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Notification not found',
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        $cancelled = $this->notificationService->cancel($notification);

        if (!$cancelled) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_STATUS',
                    'message' => 'Cannot cancel notification in current status',
                    'details' => [
                        'current_status' => $notification->status->value,
                        'allowed_statuses' => ['pending', 'queued'],
                    ],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
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
        try {
            $batchId = $request->query('batch_id');
            $stats = $this->notificationService->getStats($batchId);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'An error occurred while fetching statistics',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
