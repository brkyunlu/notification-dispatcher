<?php

namespace Tests\Unit\Services;

use App\Modules\Notification\Models\Notification;
use App\Modules\Template\Exceptions\TemplateException;
use App\Modules\Template\Models\Template;
use App\Modules\Template\Services\TemplateService;
use App\Shared\Enums\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for TemplateService
 * 
 * Tests template CRUD, variable extraction, rendering, and usage validation.
 */
class TemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    private TemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TemplateService();
    }

    /** @test */
    public function it_creates_template_and_auto_generates_slug_from_name()
    {
        $template = $this->service->create([
            'name' => 'Welcome Email Template',
            'channel' => Channel::EMAIL,
            'content' => 'Hello {{name}}',
        ]);

        $this->assertEquals('welcome-email-template', $template->slug);
    }

    /** @test */
    public function it_extracts_variables_from_content_on_create()
    {
        $template = $this->service->create([
            'name' => 'Test Template',
            'slug' => 'test',
            'channel' => Channel::EMAIL,
            'content' => 'Hello {{name}}, your order {{order_id}} is ready!',
        ]);

        $this->assertContains('name', $template->variables);
        $this->assertContains('order_id', $template->variables);
    }

    /** @test */
    public function it_extracts_variables_from_subject_on_create()
    {
        $template = $this->service->create([
            'name' => 'Email with Subject',
            'slug' => 'email-subject',
            'channel' => Channel::EMAIL,
            'content' => 'Body text',
            'subject' => 'Welcome {{name}} to {{app_name}}',
        ]);

        $this->assertContains('name', $template->variables);
        $this->assertContains('app_name', $template->variables);
    }

    /** @test */
    public function it_extracts_unique_variables_from_content_and_subject()
    {
        $template = $this->service->create([
            'name' => 'Combined Variables',
            'slug' => 'combined',
            'channel' => Channel::EMAIL,
            'content' => 'Hello {{name}}, balance: {{balance}}',
            'subject' => 'Welcome {{name}}', // Duplicate
        ]);

        // Should have unique variables only
        $this->assertCount(2, $template->variables);
        $this->assertContains('name', $template->variables);
        $this->assertContains('balance', $template->variables);
    }

    /** @test */
    public function it_updates_template_and_re_extracts_variables()
    {
        $template = Template::create([
            'name' => 'Original',
            'slug' => 'original',
            'channel' => Channel::EMAIL,
            'content' => 'Hello {{name}}',
            'variables' => ['name'],
        ]);

        $updated = $this->service->update($template, [
            'content' => 'Hello {{name}}, code: {{code}}',
        ]);

        $this->assertCount(2, $updated->variables);
        $this->assertContains('name', $updated->variables);
        $this->assertContains('code', $updated->variables);
    }

    /** @test */
    public function it_does_not_re_extract_variables_if_content_unchanged()
    {
        $template = Template::create([
            'name' => 'Test',
            'slug' => 'test',
            'channel' => Channel::EMAIL,
            'content' => 'Hello {{name}}',
            'variables' => ['name', 'extra'], // Manually set
        ]);

        $updated = $this->service->update($template, [
            'name' => 'Updated Name',
        ]);

        // Should keep old variables if content/subject not changed
        $this->assertContains('extra', $updated->variables);
    }

    /** @test */
    public function it_prevents_deletion_of_template_in_use()
    {
        $template = Template::create([
            'name' => 'In Use',
            'slug' => 'in-use',
            'channel' => Channel::EMAIL,
            'content' => 'Test',
        ]);

        // Create notification using this template
        Notification::factory()->create([
            'template_id' => $template->id,
        ]);

        $this->expectException(TemplateException::class);

        $this->service->delete($template);
    }

    /** @test */
    public function it_deletes_template_not_in_use()
    {
        $template = Template::create([
            'name' => 'Not Used',
            'slug' => 'not-used',
            'channel' => Channel::EMAIL,
            'content' => 'Test',
        ]);

        $result = $this->service->delete($template);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('templates', ['id' => $template->id]);
    }

    /** @test */
    public function it_renders_template_with_variables()
    {
        $template = Template::create([
            'name' => 'Render Test',
            'slug' => 'render-test',
            'channel' => Channel::EMAIL,
            'content' => 'Hello {{name}}, your balance is {{balance}}.',
            'subject' => 'Welcome {{name}}',
        ]);

        $rendered = $this->service->render($template, [
            'name' => 'John',
            'balance' => '$100',
        ]);

        $this->assertEquals('Hello John, your balance is $100.', $rendered['content']);
        $this->assertEquals('Welcome John', $rendered['subject']);
    }

    /** @test */
    public function it_finds_template_by_uuid()
    {
        $template = Template::create([
            'name' => 'Find by UUID',
            'slug' => 'find-uuid',
            'channel' => Channel::EMAIL,
            'content' => 'Test',
        ]);

        $found = $this->service->find($template->id);

        $this->assertNotNull($found);
        $this->assertEquals($template->id, $found->id);
    }

    /** @test */
    public function it_finds_template_by_slug()
    {
        $template = Template::create([
            'name' => 'Find by Slug',
            'slug' => 'find-slug',
            'channel' => Channel::EMAIL,
            'content' => 'Test',
        ]);

        $found = $this->service->find('find-slug');

        $this->assertNotNull($found);
        $this->assertEquals($template->id, $found->id);
    }

    /** @test */
    public function it_returns_null_for_non_existent_template()
    {
        $found = $this->service->find('non-existent-slug');

        $this->assertNull($found);
    }

    /** @test */
    public function it_lists_templates_with_channel_filter()
    {
        Template::create(['name' => 'Email', 'slug' => 'email', 'channel' => Channel::EMAIL, 'content' => 'test']);
        Template::create(['name' => 'SMS', 'slug' => 'sms', 'channel' => Channel::SMS, 'content' => 'test']);

        $results = $this->service->list(['channel' => Channel::EMAIL]);

        $this->assertEquals(1, $results->total());
        $this->assertEquals(Channel::EMAIL, $results->first()->channel);
    }

    /** @test */
    public function it_lists_templates_with_is_active_filter()
    {
        Template::create(['name' => 'Active', 'slug' => 'active', 'channel' => Channel::EMAIL, 'content' => 'test', 'is_active' => true]);
        Template::create(['name' => 'Inactive', 'slug' => 'inactive', 'channel' => Channel::EMAIL, 'content' => 'test', 'is_active' => false]);

        $results = $this->service->list(['is_active' => true]);

        $this->assertEquals(1, $results->total());
        $this->assertTrue($results->first()->is_active);
    }

    /** @test */
    public function it_searches_templates_by_name()
    {
        Template::create(['name' => 'Welcome Email', 'slug' => 'welcome', 'channel' => Channel::EMAIL, 'content' => 'test']);
        Template::create(['name' => 'Reset Password', 'slug' => 'reset', 'channel' => Channel::EMAIL, 'content' => 'test']);

        $results = $this->service->list(['search' => 'Welcome']);

        $this->assertEquals(1, $results->total());
        $this->assertStringContainsString('Welcome', $results->first()->name);
    }

    /** @test */
    public function it_searches_templates_by_slug()
    {
        Template::create(['name' => 'Test 1', 'slug' => 'welcome-email', 'channel' => Channel::EMAIL, 'content' => 'test']);
        Template::create(['name' => 'Test 2', 'slug' => 'goodbye-email', 'channel' => Channel::EMAIL, 'content' => 'test']);

        $results = $this->service->list(['search' => 'welcome']);

        $this->assertEquals(1, $results->total());
        $this->assertStringContainsString('welcome', $results->first()->slug);
    }

    /** @test */
    public function it_sorts_templates_by_created_at_desc_by_default()
    {
        // Create templates and force different timestamps using forceFill
        $first = Template::create([
            'name' => 'First', 
            'slug' => 'first', 
            'channel' => Channel::EMAIL, 
            'content' => 'test'
        ]);
        $first->forceFill(['created_at' => now()->subHour()])->save();
        
        $second = Template::create([
            'name' => 'Second', 
            'slug' => 'second', 
            'channel' => Channel::EMAIL, 
            'content' => 'test'
        ]);
        $second->forceFill(['created_at' => now()->subMinutes(30)])->save();

        $results = $this->service->list();

        // Most recent should be first
        $this->assertEquals($second->id, $results->first()->id);
        $this->assertEquals($first->id, $results->last()->id);
    }
}
