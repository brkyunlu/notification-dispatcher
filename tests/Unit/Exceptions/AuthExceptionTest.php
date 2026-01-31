<?php

namespace Tests\Unit\Exceptions;

use App\Modules\Auth\Exceptions\AuthException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AuthException static factory methods.
 */
class AuthExceptionTest extends TestCase
{
    /** @test */
    public function it_creates_missing_api_key_exception()
    {
        $e = AuthException::missingApiKey();
        $this->assertInstanceOf(AuthException::class, $e);
        $this->assertStringContainsString('API key is required', $e->getMessage());
        $this->assertEquals(401, $e->getCode());
    }

    /** @test */
    public function it_creates_invalid_api_key_exception()
    {
        $e = AuthException::invalidApiKey();
        $this->assertInstanceOf(AuthException::class, $e);
        $this->assertStringContainsString('Invalid API key', $e->getMessage());
        $this->assertEquals(401, $e->getCode());
    }

    /** @test */
    public function it_creates_deactivated_exception()
    {
        $e = AuthException::deactivated();
        $this->assertInstanceOf(AuthException::class, $e);
        $this->assertStringContainsString('deactivated', $e->getMessage());
        $this->assertEquals(401, $e->getCode());
    }

    /** @test */
    public function it_creates_expired_exception()
    {
        $e = AuthException::expired();
        $this->assertInstanceOf(AuthException::class, $e);
        $this->assertStringContainsString('expired', $e->getMessage());
        $this->assertEquals(401, $e->getCode());
    }

    /** @test */
    public function it_creates_insufficient_permissions_exception()
    {
        $e = AuthException::insufficientPermissions('write');
        $this->assertInstanceOf(AuthException::class, $e);
        $this->assertStringContainsString('Insufficient permissions', $e->getMessage());
        $this->assertStringContainsString('write', $e->getMessage());
        $this->assertEquals(403, $e->getCode());
    }
}
