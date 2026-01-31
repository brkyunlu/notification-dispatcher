<?php

namespace Tests\Unit\Enums;

use App\Shared\Enums\Channel;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Channel enum (cases, values).
 */
class ChannelTest extends TestCase
{
    /** @test */
    public function it_has_expected_cases()
    {
        $cases = Channel::cases();
        $this->assertCount(3, $cases);
        $this->assertContains(Channel::SMS, $cases);
        $this->assertContains(Channel::EMAIL, $cases);
        $this->assertContains(Channel::PUSH, $cases);
    }

    /** @test */
    public function it_returns_string_values()
    {
        $this->assertEquals('sms', Channel::SMS->value);
        $this->assertEquals('email', Channel::EMAIL->value);
        $this->assertEquals('push', Channel::PUSH->value);
    }

    /** @test */
    public function it_values_returns_all_values_array()
    {
        $values = Channel::values();
        $this->assertEqualsCanonicalizing(['sms', 'email', 'push'], $values);
    }
}
