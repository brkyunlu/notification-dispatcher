<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\ApiKey;
use App\Modules\Notification\Models\Notification;
use App\Modules\Template\Models\Template;
use App\Shared\Enums\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for Template API endpoints
 *
 * Covers TemplateController, StoreTemplateRequest, TemplateResource (index, show, store, update, destroy, render).
 */
class TemplateApiTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $key = ApiKey::create([
            'key' => 'test_key_' . bin2hex(random_bytes(16)),
            'name' => 'Test Key',
            'permissions' => ['read', 'write'],
            'expires_at' => now()->addYear(),
        ]);

        $this->apiKey = $key->key;
    }

    private function auth(): array
    {
        return ['Authorization' => 'Bearer ' . $this->apiKey];
    }

    /** @test */
    public function it_lists_templates_paginated()
    {
        Template::create([
            'name' => 'Welcome',
            'slug' => 'welcome',
            'channel' => Channel::EMAIL,
            'content' => 'Hello {{name}}',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/templates', $this->auth());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [['id', 'name', 'slug', 'channel', 'content', 'subject', 'variables', 'is_active', 'created_at', 'updated_at']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJson(['success' => true]);

        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    /** @test */
    public function it_filters_templates_by_channel()
    {
        Template::create(['name' => 'Email Tpl', 'slug' => 'email-tpl', 'channel' => Channel::EMAIL, 'content' => 'x', 'is_active' => true]);
        Template::create(['name' => 'SMS Tpl', 'slug' => 'sms-tpl', 'channel' => Channel::SMS, 'content' => 'x', 'is_active' => true]);

        $response = $this->getJson('/api/v1/templates?channel=email', $this->auth());

        $response->assertStatus(200);
        foreach ($response->json('data') as $item) {
            $this->assertEquals('email', $item['channel']);
        }
    }

    /** @test */
    public function it_filters_templates_by_is_active()
    {
        Template::create(['name' => 'Active', 'slug' => 'active', 'channel' => Channel::EMAIL, 'content' => 'x', 'is_active' => true]);
        Template::create(['name' => 'Inactive', 'slug' => 'inactive', 'channel' => Channel::EMAIL, 'content' => 'x', 'is_active' => false]);

        $response = $this->getJson('/api/v1/templates?is_active=1', $this->auth());

        $response->assertStatus(200);
        foreach ($response->json('data') as $item) {
            $this->assertTrue($item['is_active']);
        }
    }

    /** @test */
    public function it_shows_single_template_by_id()
    {
        $template = Template::create([
            'name' => 'Show Test',
            'slug' => 'show-test',
            'channel' => Channel::EMAIL,
            'content' => 'Hello {{name}}',
            'subject' => 'Hi {{name}}',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/templates/' . $template->id, $this->auth());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'name', 'slug', 'channel', 'content', 'subject', 'variables', 'description', 'is_active', 'created_at', 'updated_at'],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $template->id,
                    'name' => 'Show Test',
                    'slug' => 'show-test',
                    'channel' => 'email',
                    'content' => 'Hello {{name}}',
                    'subject' => 'Hi {{name}}',
                ],
            ]);
    }

    /** @test */
    public function it_shows_single_template_by_slug()
    {
        $template = Template::create([
            'name' => 'Slug Test',
            'slug' => 'slug-test',
            'channel' => Channel::SMS,
            'content' => 'SMS {{code}}',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/templates/slug-test', $this->auth());

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $template->id)
            ->assertJsonPath('data.slug', 'slug-test');
    }

    /** @test */
    public function it_returns_404_for_missing_template()
    {
        $response = $this->getJson('/api/v1/templates/00000000-0000-0000-0000-000000000000', $this->auth());

        $response->assertStatus(404);
    }

    /** @test */
    public function it_creates_template()
    {
        $response = $this->postJson('/api/v1/templates', [
            'name' => 'Welcome Email',
            'channel' => 'email',
            'content' => 'Hello {{name}}, welcome!',
            'subject' => 'Welcome {{name}}',
            'description' => 'Sent after signup',
            'is_active' => true,
        ], $this->auth());

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'name', 'slug', 'channel', 'content', 'subject', 'variables', 'is_active', 'created_at', 'updated_at'],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Template created successfully.',
                'data' => [
                    'name' => 'Welcome Email',
                    'channel' => 'email',
                    'content' => 'Hello {{name}}, welcome!',
                    'subject' => 'Welcome {{name}}',
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('templates', [
            'name' => 'Welcome Email',
            'channel' => Channel::EMAIL->value,
            'content' => 'Hello {{name}}, welcome!',
        ]);
    }

    /** @test */
    public function it_validates_required_fields_on_create()
    {
        $response = $this->postJson('/api/v1/templates', [], $this->auth());

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('details', $data['error'] ?? []);
    }

    /** @test */
    public function it_validates_channel_enum_on_create()
    {
        $response = $this->postJson('/api/v1/templates', [
            'name' => 'Bad Channel',
            'channel' => 'invalid',
            'content' => 'Hello',
        ], $this->auth());

        $response->assertStatus(422);
    }

    /** @test */
    public function it_updates_template()
    {
        $template = Template::create([
            'name' => 'Original',
            'slug' => 'original',
            'channel' => Channel::EMAIL,
            'content' => 'Old content',
            'is_active' => true,
        ]);

        $response = $this->putJson('/api/v1/templates/' . $template->id, [
            'name' => 'Updated Name',
            'channel' => 'email',
            'content' => 'New content {{name}}',
            'subject' => 'New subject',
            'is_active' => false,
        ], $this->auth());

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.content', 'New content {{name}}')
            ->assertJsonPath('data.is_active', false);

        $template->refresh();
        $this->assertEquals('Updated Name', $template->name);
        $this->assertEquals('New content {{name}}', $template->content);
        $this->assertFalse($template->is_active);
    }

    /** @test */
    public function it_returns_404_on_update_missing_template()
    {
        $response = $this->putJson('/api/v1/templates/00000000-0000-0000-0000-000000000000', [
            'name' => 'X',
            'channel' => 'email',
            'content' => 'X',
        ], $this->auth());

        $response->assertStatus(404);
    }

    /** @test */
    public function it_deletes_template()
    {
        $template = Template::create([
            'name' => 'To Delete',
            'slug' => 'to-delete',
            'channel' => Channel::EMAIL,
            'content' => 'x',
            'is_active' => true,
        ]);

        $response = $this->deleteJson('/api/v1/templates/' . $template->id, [], $this->auth());

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Template deleted successfully.']);

        $this->assertDatabaseMissing('templates', ['id' => $template->id]);
    }

    /** @test */
    public function it_returns_409_when_deleting_template_in_use()
    {
        $template = Template::create([
            'name' => 'In Use',
            'slug' => 'in-use',
            'channel' => Channel::EMAIL,
            'content' => 'x',
            'is_active' => true,
        ]);

        Notification::create([
            'recipient' => 'u@example.com',
            'channel' => Channel::EMAIL,
            'content' => 'x',
            'template_id' => $template->id,
            'status' => \App\Shared\Enums\Status::PENDING,
            'priority' => \App\Shared\Enums\Priority::NORMAL,
        ]);

        $response = $this->deleteJson('/api/v1/templates/' . $template->id, [], $this->auth());

        $response->assertStatus(409);
        $this->assertDatabaseHas('templates', ['id' => $template->id]);
    }

    /** @test */
    public function it_renders_template_with_variables()
    {
        $template = Template::create([
            'name' => 'Render Test',
            'slug' => 'render-test',
            'channel' => Channel::EMAIL,
            'content' => 'Hello {{name}}, product: {{product}}',
            'subject' => 'Hi {{name}}',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/templates/' . $template->id . '/render', [
            'variables' => ['name' => 'John', 'product' => 'Widget'],
        ], $this->auth());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'template_id',
                    'template_name',
                    'rendered' => ['content', 'subject'],
                    'variables_used',
                    'available_variables',
                ],
            ])
            ->assertJsonPath('data.template_id', $template->id)
            ->assertJsonPath('data.rendered.content', 'Hello John, product: Widget')
            ->assertJsonPath('data.rendered.subject', 'Hi John');
    }

    /** @test */
    public function it_returns_404_on_render_missing_template()
    {
        $response = $this->postJson('/api/v1/templates/00000000-0000-0000-0000-000000000000/render', [
            'variables' => ['name' => 'John'],
        ], $this->auth());

        $response->assertStatus(404);
    }

    /** @test */
    public function it_requires_read_permission_for_get_endpoints()
    {
        $readOnlyKey = ApiKey::create([
            'key' => 'read_only_' . bin2hex(random_bytes(16)),
            'name' => 'Read Only',
            'permissions' => ['read'],
            'expires_at' => now()->addYear(),
        ]);

        $response = $this->getJson('/api/v1/templates', [
            'Authorization' => 'Bearer ' . $readOnlyKey->key,
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_requires_write_permission_for_post_templates()
    {
        $readOnlyKey = ApiKey::create([
            'key' => 'read_only_' . bin2hex(random_bytes(16)),
            'name' => 'Read Only',
            'permissions' => ['read'],
            'expires_at' => now()->addYear(),
        ]);

        $response = $this->postJson('/api/v1/templates', [
            'name' => 'X',
            'channel' => 'email',
            'content' => 'X',
        ], [
            'Authorization' => 'Bearer ' . $readOnlyKey->key,
        ]);

        $response->assertStatus(403);
    }
}
