<?php

namespace Tests\Unit\Providers;

use App\Modules\Delivery\Exceptions\DeliveryException;
use App\Modules\Delivery\Providers\WebhookProvider;
use App\Modules\Notification\Models\Notification;
use App\Shared\Enums\Channel;
use App\Shared\Enums\Priority;
use App\Shared\Enums\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests for WebhookProvider (send, getName, supportsChannel).
 */
class WebhookProviderTest extends TestCase
{
    use RefreshDatabase;

    private WebhookProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        config(['notification.webhook_url' => 'https://webhook.test/send']);
        $this->provider = new WebhookProvider();
    }

    /** @test */
    public function it_returns_provider_name()
    {
        $this->assertEquals('webhook', $this->provider->getName());
    }

    /** @test */
    public function it_supports_all_channels()
    {
        $this->assertTrue($this->provider->supportsChannel('sms'));
        $this->assertTrue($this->provider->supportsChannel('email'));
        $this->assertTrue($this->provider->supportsChannel('push'));
        $this->assertFalse($this->provider->supportsChannel('invalid'));
    }

    /** @test */
    public function it_sends_notification_successfully()
    {
        Http::fake([
            'https://webhook.test/send' => Http::response(['ok' => true], 200),
        ]);

        $notification = Notification::factory()->email()->create([
            'content' => 'Test content',
            'subject' => 'Test subject',
            'priority' => Priority::NORMAL,
            'metadata' => ['key' => 'value'],
        ]);

        $result = $this->provider->send($notification);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message_id', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('provider', $result);
        $this->assertEquals($notification->id, $result['message_id']);
        $this->assertEquals('sent', $result['status']);
        $this->assertEquals('webhook', $result['provider']);

        Http::assertSentCount(1);
    }

    /** @test */
    public function it_throws_on_http_error()
    {
        Http::fake([
            'https://webhook.test/send' => Http::response([], 500),
        ]);

        $notification = Notification::factory()->create();

        $this->expectException(DeliveryException::class);
        $this->provider->send($notification);
    }

    /** @test */
    public function it_throws_on_connection_failure()
    {
        Http::fake(function () {
            throw new \Exception('Connection refused');
        });

        $notification = Notification::factory()->create();

        $this->expectException(DeliveryException::class);
        $this->expectExceptionMessage('Delivery failed');

        $this->provider->send($notification);
    }
}
