<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for Metrics API endpoint
 *
 * Covers MetricsController (GET /api/v1/metrics) - system metrics for monitoring.
 */
class MetricsApiTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $key = ApiKey::create([
            'key' => 'test_key_' . bin2hex(random_bytes(16)),
            'name' => 'Test Key',
            'permissions' => ['read'],
            'expires_at' => now()->addYear(),
        ]);

        $this->apiKey = $key->key;
    }

    /** @test */
    public function it_returns_metrics_with_read_permission()
    {
        $response = $this->getJson('/api/v1/metrics', [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'timestamp',
                    'notifications' => [
                        'last_24h' => [
                            'total',
                            'by_status',
                            'by_channel',
                            'by_priority',
                            'success_rate_percent',
                        ],
                        'scheduled_pending',
                    ],
                    'queue' => [
                        'connection',
                        'status',
                    ],
                    'database' => [
                        'status',
                        'connection',
                    ],
                    'cache' => [
                        'status',
                        'driver',
                    ],
                    'rate_limiting',
                ],
            ])
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_denies_metrics_without_auth()
    {
        $response = $this->getJson('/api/v1/metrics');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_denies_metrics_with_write_only_key()
    {
        $writeOnlyKey = ApiKey::create([
            'key' => 'write_only_' . bin2hex(random_bytes(16)),
            'name' => 'Write Only',
            'permissions' => ['write'],
            'expires_at' => now()->addYear(),
        ]);

        $response = $this->getJson('/api/v1/metrics', [
            'Authorization' => 'Bearer ' . $writeOnlyKey->key,
        ]);

        $response->assertStatus(403);
    }
}
