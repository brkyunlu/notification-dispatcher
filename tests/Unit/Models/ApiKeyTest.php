<?php

namespace Tests\Unit\Models;

use App\Modules\Auth\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for ApiKey model (generateKey, isValid, hasPermission, canRead, canWrite, scopes).
 */
class ApiKeyTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_generates_key_with_prefix()
    {
        $key = ApiKey::generateKey();
        $this->assertStringStartsWith('ndk_', $key);
        $this->assertGreaterThanOrEqual(52, strlen($key));
    }

    /** @test */
    public function it_is_valid_when_active_and_not_expired()
    {
        $apiKey = ApiKey::create([
            'key' => ApiKey::generateKey(),
            'name' => 'Valid',
            'permissions' => ['read'],
            'expires_at' => now()->addYear(),
            'is_active' => true,
        ]);
        $this->assertTrue($apiKey->isValid());
    }

    /** @test */
    public function it_is_invalid_when_inactive()
    {
        $apiKey = ApiKey::create([
            'key' => ApiKey::generateKey(),
            'name' => 'Inactive',
            'permissions' => ['read'],
            'expires_at' => now()->addYear(),
            'is_active' => false,
        ]);
        $this->assertFalse($apiKey->isValid());
    }

    /** @test */
    public function it_is_invalid_when_expired()
    {
        $apiKey = ApiKey::create([
            'key' => ApiKey::generateKey(),
            'name' => 'Expired',
            'permissions' => ['read'],
            'expires_at' => now()->subDay(),
            'is_active' => true,
        ]);
        $this->assertFalse($apiKey->isValid());
    }

    /** @test */
    public function it_is_valid_when_expires_at_null()
    {
        $apiKey = ApiKey::create([
            'key' => ApiKey::generateKey(),
            'name' => 'No expiry',
            'permissions' => ['read'],
            'expires_at' => null,
            'is_active' => true,
        ]);
        $this->assertTrue($apiKey->isValid());
    }

    /** @test */
    public function it_has_permission_when_permission_in_array()
    {
        $apiKey = ApiKey::create([
            'key' => ApiKey::generateKey(),
            'name' => 'Read',
            'permissions' => ['read'],
            'expires_at' => now()->addYear(),
            'is_active' => true,
        ]);
        $this->assertTrue($apiKey->hasPermission('read'));
        $this->assertFalse($apiKey->hasPermission('write'));
    }

    /** @test */
    public function it_has_admin_permission_grants_all()
    {
        $apiKey = ApiKey::create([
            'key' => ApiKey::generateKey(),
            'name' => 'Admin',
            'permissions' => ['admin'],
            'expires_at' => now()->addYear(),
            'is_active' => true,
        ]);
        $this->assertTrue($apiKey->hasAdminPermission());
        $this->assertTrue($apiKey->hasPermission('read'));
        $this->assertTrue($apiKey->hasPermission('write'));
    }

    /** @test */
    public function it_can_read_when_has_read_or_write()
    {
        $readKey = ApiKey::create([
            'key' => ApiKey::generateKey(),
            'name' => 'Read',
            'permissions' => ['read'],
            'expires_at' => now()->addYear(),
            'is_active' => true,
        ]);
        $writeKey = ApiKey::create([
            'key' => ApiKey::generateKey(),
            'name' => 'Write',
            'permissions' => ['write'],
            'expires_at' => now()->addYear(),
            'is_active' => true,
        ]);
        $this->assertTrue($readKey->canRead());
        $this->assertTrue($writeKey->canRead());
    }

    /** @test */
    public function it_can_write_only_when_has_write()
    {
        $readKey = ApiKey::create([
            'key' => ApiKey::generateKey(),
            'name' => 'Read',
            'permissions' => ['read'],
            'expires_at' => now()->addYear(),
            'is_active' => true,
        ]);
        $writeKey = ApiKey::create([
            'key' => ApiKey::generateKey(),
            'name' => 'Write',
            'permissions' => ['write'],
            'expires_at' => now()->addYear(),
            'is_active' => true,
        ]);
        $this->assertFalse($readKey->canWrite());
        $this->assertTrue($writeKey->canWrite());
    }

    /** @test */
    public function it_marks_as_used_updates_last_used_at()
    {
        $apiKey = ApiKey::create([
            'key' => ApiKey::generateKey(),
            'name' => 'Key',
            'permissions' => ['read'],
            'expires_at' => now()->addYear(),
            'is_active' => true,
            'last_used_at' => null,
        ]);
        $this->assertNull($apiKey->last_used_at);
        $apiKey->markAsUsed();
        $apiKey->refresh();
        $this->assertNotNull($apiKey->last_used_at);
    }

    /** @test */
    public function it_scope_active_filters_active_keys()
    {
        ApiKey::create([
            'key' => ApiKey::generateKey(),
            'name' => 'Active',
            'permissions' => ['read'],
            'expires_at' => now()->addYear(),
            'is_active' => true,
        ]);
        ApiKey::create([
            'key' => ApiKey::generateKey(),
            'name' => 'Inactive',
            'permissions' => ['read'],
            'expires_at' => now()->addYear(),
            'is_active' => false,
        ]);
        $active = ApiKey::active()->get();
        $this->assertCount(1, $active);
        $this->assertTrue($active->first()->is_active);
    }

    /** @test */
    public function it_scope_valid_filters_active_and_not_expired()
    {
        ApiKey::create([
            'key' => ApiKey::generateKey(),
            'name' => 'Valid',
            'permissions' => ['read'],
            'expires_at' => now()->addYear(),
            'is_active' => true,
        ]);
        ApiKey::create([
            'key' => ApiKey::generateKey(),
            'name' => 'Expired',
            'permissions' => ['read'],
            'expires_at' => now()->subDay(),
            'is_active' => true,
        ]);
        $valid = ApiKey::valid()->get();
        $this->assertCount(1, $valid);
        $this->assertTrue($valid->first()->isValid());
    }
}
