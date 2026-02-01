<?php

namespace Tests\Unit\Enums;

use App\Shared\Enums\Priority;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Priority enum (cases, values).
 */
class PriorityTest extends TestCase
{
    /** @test */
    public function it_has_expected_cases()
    {
        $cases = Priority::cases();
        $this->assertCount(3, $cases);
        $this->assertContains(Priority::LOW, $cases);
        $this->assertContains(Priority::NORMAL, $cases);
        $this->assertContains(Priority::HIGH, $cases);
    }

    /** @test */
    public function it_returns_string_values()
    {
        $this->assertEquals('low', Priority::LOW->value);
        $this->assertEquals('normal', Priority::NORMAL->value);
        $this->assertEquals('high', Priority::HIGH->value);
    }

    /** @test */
    public function it_values_returns_all_values_array()
    {
        $values = Priority::values();
        $this->assertEqualsCanonicalizing(['low', 'normal', 'high'], $values);
    }
}
