<?php

namespace App\Modules\Observability\Providers;

use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SemConv\ResourceAttributes;

/**
 * TracingServiceProvider
 * 
 * Initializes OpenTelemetry distributed tracing
 */
class TracingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register tracer in container
        $this->app->singleton(TracerInterface::class, function ($app) {
            if (!config('tracing.enabled', false)) {
                return Globals::tracerProvider()->getTracer('noop');
            }

            return $this->createTracer();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (!config('tracing.enabled', false)) {
            return;
        }

        // Set global tracer provider
        $tracerProvider = $this->createTracerProvider();
        \OpenTelemetry\SDK\Common\Util\ShutdownHandler::register([$tracerProvider, 'shutdown']);
    }

    /**
     * Create OpenTelemetry tracer
     */
    private function createTracer(): TracerInterface
    {
        $tracerProvider = $this->createTracerProvider();
        return $tracerProvider->getTracer(
            config('tracing.service_name', 'notification-dispatcher'),
            '1.0.0'
        );
    }

    /**
     * Create tracer provider with OTLP exporter
     */
    private function createTracerProvider(): TracerProvider
    {
        // Create resource with service metadata
        $resource = ResourceInfo::create(Attributes::create([
            ResourceAttributes::SERVICE_NAME => config('tracing.service_name', 'notification-dispatcher'),
            ResourceAttributes::SERVICE_VERSION => '1.0.0',
            ResourceAttributes::DEPLOYMENT_ENVIRONMENT_NAME => config('app.env', 'production'),
        ]));

        // Create OTLP exporter
        $transport = (new \OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory())->create(
            rtrim(config('tracing.endpoint', 'http://jaeger:4318'), '/') . '/v1/traces',
            'application/json'
        );
        $exporter = new SpanExporter($transport);

        // Create span processor
        // SimpleSpanProcessor is used to keep dependencies minimal and avoid SDK clock wiring.
        $spanProcessor = new SimpleSpanProcessor($exporter);

        // Create sampler based on sample rate
        $sampleRate = config('tracing.sample_rate', 1.0);
        $sampler = $sampleRate >= 1.0
            ? new AlwaysOnSampler()
            : new ParentBased(new TraceIdRatioBasedSampler($sampleRate));

        // Create and return tracer provider
        return TracerProvider::builder()
            ->addSpanProcessor($spanProcessor)
            ->setResource($resource)
            ->setSampler($sampler)
            ->build();
    }
}
