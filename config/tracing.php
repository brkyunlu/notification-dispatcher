<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Distributed Tracing
    |--------------------------------------------------------------------------
    |
    | Enable/disable distributed tracing with OpenTelemetry
    |
    */

    'enabled' => env('TRACING_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Service Name
    |--------------------------------------------------------------------------
    |
    | The name of this service in the distributed tracing system
    |
    */

    'service_name' => env('TRACING_SERVICE_NAME', 'notification-dispatcher'),

    /*
    |--------------------------------------------------------------------------
    | OTLP Endpoint
    |--------------------------------------------------------------------------
    |
    | The OpenTelemetry Protocol (OTLP) endpoint for exporting traces
    | Default: Jaeger OTLP endpoint
    |
    */

    'endpoint' => env('TRACING_ENDPOINT', 'http://jaeger:4318'),

    /*
    |--------------------------------------------------------------------------
    | Sample Rate
    |--------------------------------------------------------------------------
    |
    | The percentage of requests to trace (0.0 to 1.0)
    | 1.0 = trace all requests
    | 0.1 = trace 10% of requests
    |
    */

    'sample_rate' => env('TRACING_SAMPLE_RATE', 1.0),

    /*
    |--------------------------------------------------------------------------
    | Instrumentation
    |--------------------------------------------------------------------------
    |
    | Which components to instrument with tracing
    |
    */

    'instrumentation' => [
        'http' => env('TRACING_INSTRUMENT_HTTP', true),
        'queue' => env('TRACING_INSTRUMENT_QUEUE', true),
        'database' => env('TRACING_INSTRUMENT_DATABASE', false),
        'cache' => env('TRACING_INSTRUMENT_CACHE', false),
    ],
];
