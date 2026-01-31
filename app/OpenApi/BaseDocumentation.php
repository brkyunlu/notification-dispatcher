<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Base OpenAPI documentation for Notification Dispatcher API
 */
#[OA\OpenApi(
    openapi: '3.0.0',
    info: new OA\Info(
        title: 'Notification Dispatcher API',
        version: '1.0.0',
        description: 'Multi-channel (SMS, Email, Push) notification system API. Requires API key via Authorization: Bearer <api_key>.'
    ),
    servers: [
        new OA\Server(url: '/', description: 'API base URL'),
    ],
    security: [['bearerAuth' => []]],
    tags: [
        new OA\Tag(name: 'Notifications', description: 'Notification CRUD and stats'),
        new OA\Tag(name: 'Templates', description: 'Template CRUD and render'),
        new OA\Tag(name: 'Observability', description: 'Health and metrics'),
    ]
)]
class BaseDocumentation
{
}
