<?php

namespace Tests\Unit\Exceptions;

use App\Modules\Template\Exceptions\TemplateException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TemplateException static factory methods.
 */
class TemplateExceptionTest extends TestCase
{
    /** @test */
    public function it_creates_duplicate_slug_exception()
    {
        $e = TemplateException::duplicateSlug('Welcome');
        $this->assertInstanceOf(TemplateException::class, $e);
        $this->assertStringContainsString('already exists', $e->getMessage());
        $this->assertEquals(409, $e->getCode());
    }

    /** @test */
    public function it_creates_in_use_exception()
    {
        $e = TemplateException::inUse(5);
        $this->assertInstanceOf(TemplateException::class, $e);
        $this->assertStringContainsString('Cannot delete', $e->getMessage());
        $this->assertStringContainsString('5', $e->getMessage());
        $this->assertEquals(409, $e->getCode());
    }

    /** @test */
    public function it_creates_not_found_exception()
    {
        $e = TemplateException::notFound('slug-or-uuid');
        $this->assertInstanceOf(TemplateException::class, $e);
        $this->assertStringContainsString('Template not found', $e->getMessage());
        $this->assertEquals(404, $e->getCode());
    }
}
