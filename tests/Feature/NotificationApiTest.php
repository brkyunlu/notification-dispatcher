<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\ApiKey;
use App\Modules\Notification\Jobs\ProcessNotificationJob;
use App\Modules\Notification\Models\Notification;
use App\Modules\Template\Models\Template;
use App\Shared\Enums\Channel;
use App\Shared\Enums\Priority;
use App\Shared\Enums\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Feature tests for Notification API endpoints
 * 
 * Tests public HTTP contracts: POST (single/batch), GET (list/show), DELETE, stats.
 * Validates authentication, validation, idempotency, pagination, filtering.
 */
class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create API key with write permission
        $key = ApiKey::create([
            'key' => 'test_key_' . bin2hex(random_bytes(16)),
            'name' => 'Test Key',
            'permissions' => ['read', 'write'],
            'expires_at' => now()->addYear(),
        ]);
        
        $this->apiKey = $key->key;
        
        Queue::fake(); // Prevent actual job dispatch
    }

    /** @test */
    public function it_creates_single_notification()
    {
        $response = $this->postJson('/api/v1/notifications', [
            'recipient' => 'test@example.com',
            'channel' => 'email',
            'content' => 'Test message',
            'subject' => 'Test',
            'priority' => 'normal',
        ], [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'recipient',
                    'channel',
                    'content',
                    'subject',
                    'priority',
                    'status',
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('notifications', [
            'recipient' => 'test@example.com',
            'channel' => Channel::EMAIL->value,
            'content' => 'Test message',
        ]);

        Queue::assertPushed(ProcessNotificationJob::class);
    }

    /** @test */
    public function it_requires_recipient_channel_and_content()
    {
        $response = $this->postJson('/api/v1/notifications', [], [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error' => ['code', 'message', 'details'],
            ])
            ->assertJson([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
        
        // Check that details contain validation errors
        $details = $response->json('error.details');
        $this->assertArrayHasKey('recipient', $details);
        $this->assertArrayHasKey('channel', $details);
    }

    /** @test */
    public function it_validates_channel_enum()
    {
        $response = $this->postJson('/api/v1/notifications', [
            'recipient' => 'test@example.com',
            'channel' => 'invalid',
            'content' => 'Test',
        ], [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error' => ['code', 'message', 'details'],
            ]);
        
        $details = $response->json('error.details');
        $this->assertArrayHasKey('channel', $details);
    }

    /** @test */
    public function it_validates_priority_enum()
    {
        $response = $this->postJson('/api/v1/notifications', [
            'recipient' => 'test@example.com',
            'channel' => 'email',
            'content' => 'Test',
            'priority' => 'invalid',
        ], [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error' => ['code', 'message', 'details'],
            ]);
        
        $details = $response->json('error.details');
        $this->assertArrayHasKey('priority', $details);
    }

    /** @test */
    public function it_supports_scheduled_at_future_date()
    {
        $futureDate = now()->addHour()->toIso8601String();

        $response = $this->postJson('/api/v1/notifications', [
            'recipient' => 'test@example.com',
            'channel' => 'email',
            'content' => 'Scheduled message',
            'scheduled_at' => $futureDate,
        ], [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        $response->assertStatus(201);

        // Should not be dispatched yet (scheduled)
        Queue::assertNotPushed(ProcessNotificationJob::class);

        $this->assertDatabaseHas('notifications', [
            'content' => 'Scheduled message',
            'status' => Status::PENDING->value, // Pending until scheduled time
        ]);
    }

    /** @test */
    public function it_rejects_past_scheduled_at_date()
    {
        $pastDate = now()->subHour()->toIso8601String();

        $response = $this->postJson('/api/v1/notifications', [
            'recipient' => 'test@example.com',
            'channel' => 'email',
            'content' => 'Test',
            'scheduled_at' => $pastDate,
        ], [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error' => ['code', 'message', 'details'],
            ]);
        
        $details = $response->json('error.details');
        $this->assertArrayHasKey('scheduled_at', $details);
    }

    /** @test */
    public function it_enforces_idempotency_with_duplicate_key()
    {
        $idempotencyKey = 'unique-key-123';

        // First request
        $firstResponse = $this->postJson('/api/v1/notifications', [
            'recipient' => 'test@example.com',
            'channel' => 'email',
            'content' => 'Test',
        ], [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'X-Idempotency-Key' => $idempotencyKey,
        ]);
        
        $firstResponse->assertStatus(201);
        $firstNotificationId = $firstResponse->json('data.id');

        // Second request with same key (should return cached response)
        $response = $this->postJson('/api/v1/notifications', [
            'recipient' => 'different@example.com',
            'channel' => 'sms',
            'content' => 'Different message',
        ], [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'X-Idempotency-Key' => $idempotencyKey,
        ]);

        // Should return 201 with cached response (not create new notification)
        $response->assertStatus(201)
            ->assertHeader('X-Idempotency-Replayed', 'true');
        
        // Should return same notification ID
        $this->assertEquals($firstNotificationId, $response->json('data.id'));

        // Only one notification should exist
        $this->assertEquals(1, Notification::count());
    }

    /** @test */
    public function it_creates_batch_notifications()
    {
        $response = $this->postJson('/api/v1/notifications/batch', [
            'notifications' => [
                [
                    'recipient' => 'user1@example.com',
                    'channel' => 'email',
                    'content' => 'Message 1',
                    'priority' => 'normal',
                ],
                [
                    'recipient' => 'user2@example.com',
                    'channel' => 'email',
                    'content' => 'Message 2',
                    'priority' => 'high',
                ],
            ],
        ], [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'batch_id',
                    'count',
                    'notifications',
                ],
            ])
            ->assertJson([
                'data' => [
                    'count' => 2,
                ],
            ]);

        // Both should have same batch_id
        $batchId = $response->json('data.batch_id');
        $this->assertEquals(2, Notification::where('batch_id', $batchId)->count());

        Queue::assertPushed(ProcessNotificationJob::class, 2);
    }

    /** @test */
    public function it_validates_batch_max_1000_items()
    {
        $notifications = [];
        for ($i = 0; $i < 1001; $i++) {
            $notifications[] = [
                'recipient' => "user{$i}@example.com",
                'channel' => 'email',
                'content' => "Message {$i}",
            ];
        }

        $response = $this->postJson('/api/v1/notifications/batch', [
            'notifications' => $notifications,
        ], [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error' => ['code', 'message', 'details'],
            ]);
        
        $details = $response->json('error.details');
        $this->assertArrayHasKey('notifications', $details);
    }

    /** @test */
    public function it_validates_each_batch_item()
    {
        $response = $this->postJson('/api/v1/notifications/batch', [
            'notifications' => [
                [
                    'recipient' => 'user1@example.com',
                    'channel' => 'email',
                    'content' => 'Valid',
                ],
                [
                    // Missing required fields
                    'channel' => 'sms',
                ],
            ],
        ], [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error' => ['code', 'message', 'details'],
            ]);
        
        $details = $response->json('error.details');
        // Check that at least one validation error exists for the second item
        $hasRecipientError = isset($details['notifications.1.recipient']);
        $hasContentError = isset($details['notifications.1.content']);
        
        $this->assertTrue($hasRecipientError || $hasContentError, 
            'Expected validation errors for notifications.1.recipient or notifications.1.content');
    }

    /** @test */
    public function it_lists_notifications_paginated()
    {
        Notification::factory()->count(20)->create();

        $response = $this->getJson('/api/v1/notifications', [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'recipient', 'channel', 'status'],
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ]);
    }

    /** @test */
    public function it_filters_notifications_by_status()
    {
        Notification::factory()->create(['status' => Status::SENT]);
        Notification::factory()->create(['status' => Status::FAILED]);
        Notification::factory()->create(['status' => Status::QUEUED]);

        $response = $this->getJson('/api/v1/notifications?status=sent', [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertEquals(Status::SENT->value, $data[0]['status']);
    }

    /** @test */
    public function it_filters_notifications_by_channel()
    {
        Notification::factory()->email()->create();
        Notification::factory()->sms()->create();

        $response = $this->getJson('/api/v1/notifications?channel=sms', [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertEquals(Channel::SMS->value, $data[0]['channel']);
    }

    /** @test */
    public function it_filters_notifications_by_batch_id()
    {
        $batchId = 'batch-123';
        Notification::factory()->withBatch($batchId)->create();
        Notification::factory()->create();

        $response = $this->getJson("/api/v1/notifications?batch_id={$batchId}", [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertEquals($batchId, $data[0]['batch_id']);
    }

    /** @test */
    public function it_gets_single_notification_by_id()
    {
        $notification = Notification::factory()->create();

        $response = $this->getJson("/api/v1/notifications/{$notification->id}", [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $notification->id,
                    'recipient' => $notification->recipient,
                ],
            ]);
    }

    /** @test */
    public function it_returns_404_for_non_existent_notification()
    {
        $response = $this->getJson('/api/v1/notifications/non-existent-id', [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        $response->assertStatus(404);
    }

    /** @test */
    public function it_cancels_pending_notification()
    {
        $notification = Notification::factory()->pending()->create();

        $response = $this->deleteJson("/api/v1/notifications/{$notification->id}", [], [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $notification->refresh();
        $this->assertEquals(Status::CANCELLED, $notification->status);
    }

    /** @test */
    public function it_cannot_cancel_sent_notification()
    {
        $notification = Notification::factory()->sent()->create();

        $response = $this->deleteJson("/api/v1/notifications/{$notification->id}", [], [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        $response->assertStatus(422);

        $notification->refresh();
        $this->assertEquals(Status::SENT, $notification->status);
    }

    /** @test */
    public function it_returns_global_stats()
    {
        Notification::factory()->sent()->create();
        Notification::factory()->failed()->create();
        Notification::factory()->queued()->create();

        $response = $this->getJson('/api/v1/notifications/stats', [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total',
                    'by_status',
                    'by_channel',
                    'by_priority',
                ],
            ])
            ->assertJson([
                'data' => [
                    'total' => 3,
                ],
            ]);
    }

    /** @test */
    public function it_returns_batch_specific_stats()
    {
        // Use valid UUID for batch_id (production validates UUID format)
        $batchId = \Illuminate\Support\Str::uuid()->toString();
        
        Notification::factory()->withBatch($batchId)->sent()->create();
        Notification::factory()->withBatch($batchId)->failed()->create();
        Notification::factory()->create(); // Different batch

        $response = $this->getJson("/api/v1/notifications/stats?batch_id={$batchId}", [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'total' => 2,
                ],
            ]);
    }

    /** @test */
    public function it_uses_template_when_template_id_provided()
    {
        $template = Template::create([
            'name' => 'Welcome',
            'slug' => 'welcome',
            'channel' => Channel::EMAIL,
            'content' => 'Hello {{name}}!',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/notifications', [
            'recipient' => 'test@example.com',
            'channel' => 'email',
            'template_id' => $template->id,
            'variables' => ['name' => 'John'],
        ], [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'recipient' => 'test@example.com',
            'content' => 'Hello John!',
            'template_id' => $template->id,
        ]);
    }
}
