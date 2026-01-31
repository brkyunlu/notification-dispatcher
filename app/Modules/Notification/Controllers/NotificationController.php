<?php

namespace App\Modules\Notification\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Exceptions\NotificationException;
use App\Modules\Notification\Requests\StoreBatchNotificationRequest;
use App\Modules\Notification\Requests\StoreNotificationRequest;
use App\Modules\Notification\Resources\NotificationCollection;
use App\Modules\Notification\Resources\NotificationResource;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * List notifications with filters
     */
    #[OA\Get(path: '/api/v1/notifications', summary: 'List notifications', tags: ['Notifications'])]
    #[OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'queued', 'sent', 'failed', 'cancelled']))]
    #[OA\Parameter(name: 'channel', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['sms', 'email', 'push']))]
    #[OA\Parameter(name: 'priority', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['low', 'normal', 'high']))]
    #[OA\Parameter(name: 'batch_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date-time'), description: 'Filter by created_at >= from')]
    #[OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date-time'), description: 'Filter by created_at <= to')]
    #[OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'created_at'))]
    #[OA\Parameter(name: 'sort_order', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'desc'))]
    #[OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Paginated list of notifications')]
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
     * Create a single notification
     */
    #[OA\Post(path: '/api/v1/notifications', summary: 'Create notification (single)', tags: ['Notifications'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['recipient', 'channel', 'content'],
            properties: [
                new OA\Property(property: 'recipient', type: 'string', example: 'user@example.com', description: 'Recipient (email, phone, or push token)'),
                new OA\Property(property: 'channel', type: 'string', enum: ['sms', 'email', 'push']),
                new OA\Property(property: 'content', type: 'string', example: 'Hello, this is a test.', description: 'Message body (required unless template_id)'),
                new OA\Property(property: 'subject', type: 'string', example: 'Welcome', description: 'Optional subject (e.g. for email)'),
                new OA\Property(property: 'priority', type: 'string', enum: ['low', 'normal', 'high'], description: 'Defaults to normal'),
                new OA\Property(property: 'template_id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'variables', type: 'object'),
                new OA\Property(
                    property: 'scheduled_at',
                    type: 'string',
                    format: 'date-time',
                    description: 'Optional. ISO 8601 datetime when to send (e.g. 2026-02-01T12:00:00Z). Must be in the future.',
                    example: '2026-02-01T12:00:00Z'
                ),
                new OA\Property(property: 'metadata', type: 'object'),
            ],
            example: [
                'recipient' => 'user@example.com',
                'channel' => 'email',
                'content' => 'Hello, this is a test notification.',
                'subject' => 'Test',
                'priority' => 'normal',
                'scheduled_at' => '2026-02-01T12:00:00Z',
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Notification created')]
    #[OA\Response(response: 409, description: 'Duplicate idempotency key')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function store(StoreNotificationRequest $request): JsonResponse
    {
        // No try-catch needed - NotificationException is caught by global handler
        if ($request->isBatch()) {
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

        $notification = $this->notificationService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Notification created successfully',
            'data' => new NotificationResource($notification),
        ], Response::HTTP_CREATED);
    }

    /**
     * Create notifications in batch (max 1000 per request)
     */
    #[OA\Post(path: '/api/v1/notifications/batch', summary: 'Create notifications (batch)', tags: ['Notifications'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['notifications'],
            properties: [
                new OA\Property(
                    property: 'notifications',
                    type: 'array',
                    description: 'Array of notification objects (1–1000 items)',
                    minItems: 1,
                    maxItems: 1000,
                    items: new OA\Items(
                        type: 'object',
                        required: ['recipient', 'channel', 'content'],
                        properties: [
                            new OA\Property(property: 'recipient', type: 'string'),
                            new OA\Property(property: 'channel', type: 'string', enum: ['sms', 'email', 'push']),
                            new OA\Property(property: 'content', type: 'string'),
                            new OA\Property(property: 'subject', type: 'string'),
                            new OA\Property(property: 'priority', type: 'string', enum: ['low', 'normal', 'high']),
                            new OA\Property(property: 'template_id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'variables', type: 'object'),
                            new OA\Property(
                                property: 'scheduled_at',
                                type: 'string',
                                format: 'date-time',
                                description: 'Optional. ISO 8601 datetime when to send (must be in the future).',
                                example: '2026-02-01T12:00:00Z'
                            ),
                            new OA\Property(property: 'metadata', type: 'object'),
                        ]
                    )
                ),
            ],
            example: [
                'notifications' => [
                    [
                        'recipient' => 'user1@example.com',
                        'channel' => 'email',
                        'content' => 'Hello User 1',
                        'subject' => 'Batch 1',
                        'priority' => 'normal',
                        'scheduled_at' => '2026-02-01T12:00:00Z',
                    ],
                    [
                        'recipient' => '+15551234567',
                        'channel' => 'sms',
                        'content' => 'Hello User 2',
                        'priority' => 'high',
                    ],
                ],
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Batch created (batch_id, count, notifications)')]
    #[OA\Response(response: 409, description: 'Duplicate idempotency key')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function storeBatch(StoreBatchNotificationRequest $request): JsonResponse
    {
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

    /**
     * Get notification by ID
     */
    #[OA\Get(path: '/api/v1/notifications/{id}', summary: 'Get notification by ID', tags: ['Notifications'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: 'Notification details')]
    #[OA\Response(response: 404, description: 'Notification not found')]
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
    #[OA\Delete(path: '/api/v1/notifications/{id}', summary: 'Cancel notification', tags: ['Notifications'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: 'Notification cancelled')]
    #[OA\Response(response: 404, description: 'Notification not found')]
    #[OA\Response(response: 422, description: 'Cannot cancel in current status')]
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
    #[OA\Get(path: '/api/v1/notifications/stats', summary: 'Get notification statistics', tags: ['Notifications'])]
    #[OA\Parameter(name: 'batch_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid', description: 'Filter stats by batch (optional)'))]
    #[OA\Response(response: 200, description: 'Stats: total, by_status, by_channel, by_priority')]
    #[OA\Response(response: 422, description: 'Invalid batch_id if provided')]
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
