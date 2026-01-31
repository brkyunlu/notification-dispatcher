<?php

namespace Tests\Unit\Services;

use App\Modules\Notification\Exceptions\NotificationException;
use App\Modules\Notification\Jobs\ProcessNotificationJob;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Template\Models\Template;
use App\Shared\Enums\Channel;
use App\Shared\Enums\Priority;
use App\Shared\Enums\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Unit tests for NotificationService
 * 
 * Tests business logic for creating, listing, filtering, canceling notifications.
 * Validates template application, batch creation, and stats aggregation.
 */
class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NotificationService();
        Queue::fake(); // Prevent actual job dispatch
    }

    /** @test */
    public function it_creates_notification_with_default_status_pending()
    {
        $notification = $this->service->create([
            'recipient' => 'test@example.com',
            'channel' => Channel::EMAIL,
            'content' => 'Test message',
        ]);

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertEquals(Status::QUEUED, $notification->status); // Marked queued after dispatch
        $this->assertEquals('test@example.com', $notification->recipient);
        $this->assertEquals(Channel::EMAIL, $notification->channel);
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'status' => Status::QUEUED->value,
        ]);
    }

    /** @test */
    public function it_creates_notification_with_default_priority_normal()
    {
        $notification = $this->service->create([
            'recipient' => '+1234567890',
            'channel' => Channel::SMS,
            'content' => 'Test SMS',
        ]);

        $this->assertEquals(Priority::NORMAL, $notification->priority);
    }

    /** @test */
    public function it_dispatches_notification_to_queue()
    {
        $notification = $this->service->create([
            'recipient' => 'test@example.com',
            'channel' => Channel::EMAIL,
            'content' => 'Test',
            'priority' => Priority::HIGH,
        ]);

        Queue::assertPushed(ProcessNotificationJob::class, function ($job) use ($notification) {
            return $job->notification->id === $notification->id;
        });
    }

    /** @test */
    public function it_does_not_dispatch_scheduled_notifications_immediately()
    {
        $futureDate = now()->addHour();

        $notification = $this->service->create([
            'recipient' => 'test@example.com',
            'channel' => Channel::EMAIL,
            'content' => 'Scheduled message',
            'scheduled_at' => $futureDate,
        ]);

        // Should not dispatch to queue yet
        Queue::assertNotPushed(ProcessNotificationJob::class);
        
        // Status should remain pending
        $this->assertEquals(Status::PENDING, $notification->status);
    }

    /** @test */
    public function it_creates_batch_notifications_with_same_batch_id()
    {
        $notifications = $this->service->createBatch([
            [
                'recipient' => 'user1@example.com',
                'channel' => Channel::EMAIL,
                'content' => 'Message 1',
            ],
            [
                'recipient' => 'user2@example.com',
                'channel' => Channel::EMAIL,
                'content' => 'Message 2',
            ],
        ]);

        $this->assertCount(2, $notifications);
        $this->assertNotNull($notifications[0]->batch_id);
        $this->assertEquals($notifications[0]->batch_id, $notifications[1]->batch_id);
        
        // Both should be dispatched
        Queue::assertPushed(ProcessNotificationJob::class, 2);
    }

    /** @test */
    public function it_applies_template_variables_when_template_id_provided()
    {
        $template = Template::create([
            'name' => 'Welcome Email',
            'slug' => 'welcome-email',
            'channel' => Channel::EMAIL,
            'content' => 'Hello {{name}}, welcome to {{app}}!',
            'subject' => 'Welcome {{name}}',
            'is_active' => true,
        ]);

        $notification = $this->service->create([
            'recipient' => 'test@example.com',
            // Don't specify channel - let it be taken from template
            'template_id' => $template->id,
            'variables' => ['name' => 'John', 'app' => 'MyApp'],
        ]);

        $this->assertEquals('Hello John, welcome to MyApp!', $notification->content);
        $this->assertEquals('Welcome John', $notification->subject);
        $this->assertEquals(Channel::EMAIL, $notification->channel);
    }

    /** @test */
    public function it_throws_exception_for_inactive_template()
    {
        $template = Template::create([
            'name' => 'Inactive Template',
            'slug' => 'inactive',
            'channel' => Channel::EMAIL,
            'content' => 'Test',
            'is_active' => false,
        ]);

        $this->expectException(NotificationException::class);

        $this->service->create([
            'recipient' => 'test@example.com',
            'channel' => Channel::EMAIL,
            'template_id' => $template->id,
        ]);
    }

    /** @test */
    public function it_throws_exception_for_channel_mismatch_with_template()
    {
        $template = Template::create([
            'name' => 'SMS Template',
            'slug' => 'sms-template',
            'channel' => Channel::SMS,
            'content' => 'SMS message',
            'is_active' => true,
        ]);

        $this->expectException(NotificationException::class);

        $this->service->create([
            'recipient' => 'test@example.com',
            'channel' => Channel::EMAIL, // Mismatch
            'template_id' => $template->id,
        ]);
    }

    /** @test */
    public function it_lists_notifications_with_status_filter()
    {
        Notification::factory()->create(['status' => Status::SENT]);
        Notification::factory()->create(['status' => Status::FAILED]);
        Notification::factory()->create(['status' => Status::QUEUED]);

        $results = $this->service->list(['status' => Status::SENT]);

        $this->assertEquals(1, $results->total());
        $this->assertEquals(Status::SENT, $results->first()->status);
    }

    /** @test */
    public function it_lists_notifications_with_channel_filter()
    {
        Notification::factory()->create(['channel' => Channel::EMAIL]);
        Notification::factory()->create(['channel' => Channel::SMS]);

        $results = $this->service->list(['channel' => Channel::SMS]);

        $this->assertEquals(1, $results->total());
        $this->assertEquals(Channel::SMS, $results->first()->channel);
    }

    /** @test */
    public function it_lists_notifications_with_priority_filter()
    {
        Notification::factory()->create(['priority' => Priority::HIGH]);
        Notification::factory()->create(['priority' => Priority::NORMAL]);

        $results = $this->service->list(['priority' => Priority::HIGH]);

        $this->assertEquals(1, $results->total());
        $this->assertEquals(Priority::HIGH, $results->first()->priority);
    }

    /** @test */
    public function it_lists_notifications_with_batch_id_filter()
    {
        $batchId = 'batch-123';
        Notification::factory()->create(['batch_id' => $batchId]);
        Notification::factory()->create(['batch_id' => 'batch-456']);

        $results = $this->service->list(['batch_id' => $batchId]);

        $this->assertEquals(1, $results->total());
        $this->assertEquals($batchId, $results->first()->batch_id);
    }

    /** @test */
    public function it_lists_notifications_with_date_range_filter()
    {
        $oldNotification = Notification::factory()->create([
            'created_at' => now()->subDays(5),
        ]);
        $newNotification = Notification::factory()->create([
            'created_at' => now(),
        ]);

        $results = $this->service->list([
            'from' => now()->subDays(2)->toDateString(),
        ]);

        $this->assertEquals(1, $results->total());
        $this->assertEquals($newNotification->id, $results->first()->id);
    }

    /** @test */
    public function it_sorts_notifications_by_created_at_desc_by_default()
    {
        $first = Notification::factory()->create(['created_at' => now()->subHour()]);
        $second = Notification::factory()->create(['created_at' => now()]);

        $results = $this->service->list();

        $this->assertEquals($second->id, $results->first()->id);
    }

    /** @test */
    public function it_cancels_pending_notification()
    {
        $notification = Notification::factory()->create(['status' => Status::PENDING]);

        $result = $this->service->cancel($notification);

        $this->assertTrue($result);
        $notification->refresh();
        $this->assertEquals(Status::CANCELLED, $notification->status);
    }

    /** @test */
    public function it_cancels_queued_notification()
    {
        $notification = Notification::factory()->create(['status' => Status::QUEUED]);

        $result = $this->service->cancel($notification);

        $this->assertTrue($result);
        $notification->refresh();
        $this->assertEquals(Status::CANCELLED, $notification->status);
    }

    /** @test */
    public function it_cannot_cancel_sent_notification()
    {
        $notification = Notification::factory()->create(['status' => Status::SENT]);

        $result = $this->service->cancel($notification);

        $this->assertFalse($result);
        $notification->refresh();
        $this->assertEquals(Status::SENT, $notification->status);
    }

    /** @test */
    public function it_cannot_cancel_failed_notification()
    {
        $notification = Notification::factory()->create(['status' => Status::FAILED]);

        $result = $this->service->cancel($notification);

        $this->assertFalse($result);
        $notification->refresh();
        $this->assertEquals(Status::FAILED, $notification->status);
    }

    /** @test */
    public function it_returns_global_stats()
    {
        Notification::factory()->create(['status' => Status::SENT, 'channel' => Channel::EMAIL, 'priority' => Priority::HIGH]);
        Notification::factory()->create(['status' => Status::FAILED, 'channel' => Channel::SMS, 'priority' => Priority::NORMAL]);
        Notification::factory()->create(['status' => Status::SENT, 'channel' => Channel::EMAIL, 'priority' => Priority::NORMAL]);

        $stats = $this->service->getStats();

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['by_status'][Status::SENT->value]);
        $this->assertEquals(1, $stats['by_status'][Status::FAILED->value]);
        $this->assertEquals(2, $stats['by_channel'][Channel::EMAIL->value]);
        $this->assertEquals(1, $stats['by_channel'][Channel::SMS->value]);
    }

    /** @test */
    public function it_returns_batch_specific_stats()
    {
        $batchId = 'batch-123';
        Notification::factory()->create(['batch_id' => $batchId, 'status' => Status::SENT]);
        Notification::factory()->create(['batch_id' => $batchId, 'status' => Status::FAILED]);
        Notification::factory()->create(['batch_id' => 'other-batch', 'status' => Status::SENT]);

        $stats = $this->service->getStats($batchId);

        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1, $stats['by_status'][Status::SENT->value]);
        $this->assertEquals(1, $stats['by_status'][Status::FAILED->value]);
    }
}
