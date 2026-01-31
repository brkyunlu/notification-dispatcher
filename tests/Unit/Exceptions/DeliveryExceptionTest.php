<?php

namespace Tests\Unit\Exceptions;

use App\Modules\Delivery\Exceptions\DeliveryException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DeliveryException static factory methods.
 */
class DeliveryExceptionTest extends TestCase
{
    /** @test */
    public function it_creates_circuit_breaker_open_exception()
    {
        $e = DeliveryException::circuitBreakerOpen('email');
        $this->assertInstanceOf(DeliveryException::class, $e);
        $this->assertStringContainsString('Circuit breaker', $e->getMessage());
        $this->assertEquals(503, $e->getCode());
    }

    /** @test */
    public function it_creates_rate_limit_exceeded_exception()
    {
        $e = DeliveryException::rateLimitExceeded('sms');
        $this->assertInstanceOf(DeliveryException::class, $e);
        $this->assertStringContainsString('Rate limit', $e->getMessage());
        $this->assertEquals(429, $e->getCode());
    }

    /** @test */
    public function it_creates_delivery_failed_exception()
    {
        $e = DeliveryException::deliveryFailed('Connection timeout');
        $this->assertInstanceOf(DeliveryException::class, $e);
        $this->assertStringContainsString('Delivery failed', $e->getMessage());
        $this->assertEquals(500, $e->getCode());
    }

    /** @test */
    public function it_creates_provider_error_exception()
    {
        $e = DeliveryException::providerError('webhook', 'HTTP 500');
        $this->assertInstanceOf(DeliveryException::class, $e);
        $this->assertStringContainsString('Provider webhook', $e->getMessage());
        $this->assertEquals(502, $e->getCode());
    }
}
