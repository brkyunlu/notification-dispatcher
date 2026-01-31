<?php

namespace Tests\Unit\Enums;

use App\Shared\Enums\Status;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Status enum (cases, values, isFinal).
 */
class StatusTest extends TestCase
{
    /** @test */
    public function it_has_expected_cases()
    {
        $cases = Status::cases();
        $this->assertCount(7, $cases);
        $this->assertContains(Status::PENDING, $cases);
        $this->assertContains(Status::SENT, $cases);
        $this->assertContains(Status::DELIVERED, $cases);
        $this->assertContains(Status::CANCELLED, $cases);
        $this->assertContains(Status::FAILED, $cases);
    }

    /** @test */
    public function it_returns_string_values()
    {
        $this->assertEquals('pending', Status::PENDING->value);
        $this->assertEquals('sent', Status::SENT->value);
        $this->assertEquals('delivered', Status::DELIVERED->value);
        $this->assertEquals('cancelled', Status::CANCELLED->value);
    }

    /** @test */
    public function it_values_returns_all_values_array()
    {
        $values = Status::values();
        $this->assertContains('pending', $values);
        $this->assertContains('sent', $values);
        $this->assertContains('failed', $values);
    }

    /** @test */
    public function it_is_final_for_delivered_and_cancelled()
    {
        $this->assertTrue(Status::DELIVERED->isFinal());
        $this->assertTrue(Status::CANCELLED->isFinal());
    }

    /** @test */
    public function it_is_not_final_for_failed_or_pending()
    {
        $this->assertFalse(Status::FAILED->isFinal());
        $this->assertFalse(Status::PENDING->isFinal());
        $this->assertFalse(Status::SENT->isFinal());
    }
}
