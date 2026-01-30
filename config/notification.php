<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Webhook URL
    |--------------------------------------------------------------------------
    |
    | The webhook URL where notifications will be sent for testing.
    | In production, this would be replaced with actual provider endpoints.
    |
    */

    'webhook_url' => env('NOTIFICATION_WEBHOOK_URL', 'https://httpbin.org/post'),

    /*
    |--------------------------------------------------------------------------
    | Provider HTTP Settings
    |--------------------------------------------------------------------------
    |
    | HTTP client configuration for notification providers
    |
    */

    'provider' => [
        'timeout' => env('NOTIFICATION_PROVIDER_TIMEOUT', 30),
        'retry_attempts' => env('NOTIFICATION_PROVIDER_RETRY', 2),
        'retry_delay' => env('NOTIFICATION_PROVIDER_RETRY_DELAY', 100), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limit per channel (requests per second)
    |
    */

    'rate_limit' => [
        'enabled' => env('RATE_LIMIT_ENABLED', true),
        'requests_per_second' => env('RATE_LIMIT_RPS', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for retry attempts and backoff strategy
    | Backoff strategy uses exponential backoff with custom intervals
    |
    */

    'retry' => [
        'max_attempts' => (int) env('NOTIFICATION_MAX_ATTEMPTS', 5),
        'backoff' => [
            (int) env('NOTIFICATION_BACKOFF_1', 60),    // 1st retry: 1 minute
            (int) env('NOTIFICATION_BACKOFF_2', 300),   // 2nd retry: 5 minutes
            (int) env('NOTIFICATION_BACKOFF_3', 1800),  // 3rd retry: 30 minutes
            (int) env('NOTIFICATION_BACKOFF_4', 7200),  // 4th retry: 2 hours
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    |
    | Circuit breaker configuration for provider failures
    |
    */

    'circuit_breaker' => [
        'enabled' => env('CIRCUIT_BREAKER_ENABLED', true),
        'failure_threshold' => (int) env('CIRCUIT_BREAKER_THRESHOLD', 5),
        'recovery_time' => (int) env('CIRCUIT_BREAKER_TIMEOUT', 30), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Queue names and worker counts per priority
    |
    */

    'queues' => [
        'high' => [
            'name' => 'notifications-high',
            'workers' => 4,
        ],
        'normal' => [
            'name' => 'notifications-normal',
            'workers' => 2,
        ],
        'low' => [
            'name' => 'notifications-low',
            'workers' => 1,
        ],
    ],
];
