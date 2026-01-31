<?php

namespace Tests\Unit\Exceptions;

use App\Modules\Notification\Exceptions\NotificationException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for NotificationException static factory methods.
 */
class NotificationExceptionTest extends TestCase
{
    /** @test */
    public function it_creates_duplicate_idempotency_exception()
    {
        $e = NotificationException::duplicateIdempotency('key-123');
        $this->assertInstanceOf(NotificationException::class, $e);
        $this->assertStringContainsString('already been processed', $e->getMessage());
        $this->assertEquals(409, $e->getCode());
        $this->assertArrayHasKey('idempotency_key', $e->details);
        $this->assertEquals('key-123', $e->details['idempotency_key']);
    }

    /** @test */
    public function it_creates_not_found_exception()
    {
        $e = NotificationException::notFound('uuid-123');
        $this->assertInstanceOf(NotificationException::class, $e);
        $this->assertStringContainsString('Notification not found', $e->getMessage());
        $this->assertEquals(404, $e->getCode());
    }

    /** @test */
    public function it_creates_cannot_cancel_exception()
    {
        $e = NotificationException::cannotCancel('sent', ['pending', 'queued']);
        $this->assertInstanceOf(NotificationException::class, $e);
        $this->assertStringContainsString('Cannot cancel', $e->getMessage());
        $this->assertEquals(422, $e->getCode());
        $this->assertEquals('sent', $e->details['current_status']);
        $this->assertEquals(['pending', 'queued'], $e->details['allowed_statuses']);
    }

    /** @test */
    public function it_creates_template_not_found_exception()
    {
        $e = NotificationException::templateNotFound();
        $this->assertInstanceOf(NotificationException::class, $e);
        $this->assertStringContainsString('Template not found', $e->getMessage());
        $this->assertEquals(404, $e->getCode());
    }

    /** @test */
    public function it_creates_template_inactive_exception()
    {
        $e = NotificationException::templateInactive();
        $this->assertInstanceOf(NotificationException::class, $e);
        $this->assertStringContainsString('not active', $e->getMessage());
        $this->assertEquals(400, $e->getCode());
    }

    /** @test */
    public function it_creates_channel_mismatch_exception()
    {
        $e = NotificationException::channelMismatch('email');
        $this->assertInstanceOf(NotificationException::class, $e);
        $this->assertStringContainsString('Channel mismatch', $e->getMessage());
        $this->assertStringContainsString('email', $e->getMessage());
        $this->assertEquals(400, $e->getCode());
    }

    /** @test */
    public function it_with_details_merges_details()
    {
        $e = NotificationException::duplicateIdempotency('k');
        $e->withDetails(['extra' => 'value']);
        $this->assertArrayHasKey('idempotency_key', $e->details);
        $this->assertArrayHasKey('extra', $e->details);
        $this->assertEquals('value', $e->details['extra']);
    }
}
