<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for API authentication and authorization
 * 
 * Tests API key middleware, read/write permissions, and health endpoint (no auth).
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_requires_authorization_header()
    {
        $response = $this->getJson('/api/v1/notifications');

        $response->assertStatus(401)
            ->assertJsonStructure([
                'success',
                'message',
                'error' => ['code', 'status'],
            ])
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'status' => 401,
                ],
            ]);
    }

    /** @test */
    public function it_rejects_invalid_api_key()
    {
        $response = $this->getJson('/api/v1/notifications', [
            'Authorization' => 'Bearer invalid_key',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_rejects_expired_api_key()
    {
        $key = ApiKey::create([
            'key' => 'expired_key',
            'name' => 'Expired Key',
            'permissions' => ['read'],
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/notifications', [
            'Authorization' => 'Bearer ' . $key->key,
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_allows_read_permission_for_get_endpoints()
    {
        $key = ApiKey::create([
            'key' => 'read_only_key',
            'name' => 'Read Only',
            'permissions' => ['read'],
            'expires_at' => now()->addYear(),
        ]);

        $response = $this->getJson('/api/v1/notifications', [
            'Authorization' => 'Bearer ' . $key->key,
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_denies_read_only_permission_for_post_endpoints()
    {
        $key = ApiKey::create([
            'key' => 'read_only_key',
            'name' => 'Read Only',
            'permissions' => ['read'],
            'expires_at' => now()->addYear(),
        ]);

        $response = $this->postJson('/api/v1/notifications', [
            'recipient' => 'test@example.com',
            'channel' => 'email',
            'content' => 'Test',
        ], [
            'Authorization' => 'Bearer ' . $key->key,
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_allows_write_permission_for_delete_endpoints()
    {
        $key = ApiKey::create([
            'key' => 'write_key',
            'name' => 'Write Access',
            'permissions' => ['read', 'write'],
            'expires_at' => now()->addYear(),
        ]);

        // Create a notification first
        $notification = \App\Modules\Notification\Models\Notification::factory()->pending()->create();

        $response = $this->deleteJson("/api/v1/notifications/{$notification->id}", [], [
            'Authorization' => 'Bearer ' . $key->key,
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_denies_read_only_permission_for_delete_endpoints()
    {
        $key = ApiKey::create([
            'key' => 'read_only_key',
            'name' => 'Read Only',
            'permissions' => ['read'],
            'expires_at' => now()->addYear(),
        ]);

        $notification = \App\Modules\Notification\Models\Notification::factory()->pending()->create();

        $response = $this->deleteJson("/api/v1/notifications/{$notification->id}", [], [
            'Authorization' => 'Bearer ' . $key->key,
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function health_endpoint_requires_no_authentication()
    {
        $response = $this->getJson('/health');

        // Should not return 401 (health is public)
        $this->assertNotEquals(401, $response->status());
        
        // Should return 200 or 503 depending on system health
        $this->assertContains($response->status(), [200, 503]);
    }

    /** @test */
    public function it_allows_requests_from_different_api_keys()
    {
        $key1 = ApiKey::create([
            'key' => 'key1',
            'name' => 'Key 1',
            'permissions' => ['read'],
            'expires_at' => now()->addYear(),
        ]);

        $key2 = ApiKey::create([
            'key' => 'key2',
            'name' => 'Key 2',
            'permissions' => ['read'],
            'expires_at' => now()->addYear(),
        ]);

        $response1 = $this->getJson('/api/v1/notifications', [
            'Authorization' => 'Bearer ' . $key1->key,
        ]);

        $response2 = $this->getJson('/api/v1/notifications', [
            'Authorization' => 'Bearer ' . $key2->key,
        ]);

        $response1->assertStatus(200);
        $response2->assertStatus(200);
    }
}
