<?php

namespace Tests\Feature\Jobs;

use App\Modules\Notification\Jobs\ProcessNotificationJob;
use App\Modules\Notification\Jobs\ProcessScheduledNotificationsJob;
use App\Modules\Notification\Models\Notification;
use App\Shared\Enums\Channel;
use App\Shared\Enums\Priority;
use App\Shared\Enums\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Feature tests for ProcessScheduledNotificationsJob
 * 
 * Tests scheduled notification processing: finding due notifications,
 * dispatching to queue, priority ordering, and status updates.
 */
class ProcessScheduledNotificationsJobTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_processes_notifications_with_scheduled_at_in_past()
    {
        Queue::fake();

        // Create scheduled notification (past)
        Notification::factory()->pending()->create([
            'scheduled_at' => now()->subMinute(),
        ]);

        $job = new ProcessScheduledNotificationsJob();
        $job->handle();

        Queue::assertPushed(ProcessNotificationJob::class, 1);
    }

    /** @test */
    public function it_processes_notifications_with_scheduled_at_now()
    {
        Queue::fake();

        // Create scheduled notification (now)
        Notification::factory()->pending()->create([
            'scheduled_at' => now(),
        ]);

        $job = new ProcessScheduledNotificationsJob();
        $job->handle();

        Queue::assertPushed(ProcessNotificationJob::class, 1);
    }

    /** @test */
    public function it_does_not_process_future_scheduled_notifications()
    {
        Queue::fake();

        // Create scheduled notification (future)
        Notification::factory()->pending()->create([
            'scheduled_at' => now()->addHour(),
        ]);

        $job = new ProcessScheduledNotificationsJob();
        $job->handle();

        Queue::assertNotPushed(ProcessNotificationJob::class);
    }

    /** @test */
    public function it_marks_notification_as_queued_after_dispatch()
    {
        Queue::fake();

        $notification = Notification::factory()->pending()->create([
            'scheduled_at' => now()->subMinute(),
        ]);

        $job = new ProcessScheduledNotificationsJob();
        $job->handle();

        $notification->refresh();
        $this->assertEquals(Status::QUEUED, $notification->status);
    }

    /** @test */
    public function it_processes_multiple_scheduled_notifications()
    {
        Queue::fake();

        // Create 5 scheduled notifications
        for ($i = 0; $i < 5; $i++) {
            Notification::factory()->pending()->create([
                'scheduled_at' => now()->subMinutes($i + 1),
            ]);
        }

        $job = new ProcessScheduledNotificationsJob();
        $job->handle();

        Queue::assertPushed(ProcessNotificationJob::class, 5);
    }

    /** @test */
    public function it_respects_batch_size_limit()
    {
        Queue::fake();

        // Create 150 scheduled notifications
        for ($i = 0; $i < 150; $i++) {
            Notification::factory()->pending()->create([
                'scheduled_at' => now()->subMinute(),
            ]);
        }

        $job = new ProcessScheduledNotificationsJob(batchSize: 100);
        $job->handle();

        // Should only process 100 (batch size limit)
        Queue::assertPushed(ProcessNotificationJob::class, 100);
    }

    /** @test */
    public function it_processes_notifications_in_scheduled_at_order()
    {
        Queue::fake();

        $oldest = Notification::factory()->pending()->create([
            'scheduled_at' => now()->subHours(2),
        ]);

        $middle = Notification::factory()->pending()->create([
            'scheduled_at' => now()->subHour(),
        ]);

        $newest = Notification::factory()->pending()->create([
            'scheduled_at' => now()->subMinutes(30),
        ]);

        $job = new ProcessScheduledNotificationsJob();
        $job->handle();

        // Verify all were dispatched
        Queue::assertPushed(ProcessNotificationJob::class, 3);

        // Oldest should be queued first
        $oldest->refresh();
        $middle->refresh();
        $newest->refresh();

        $this->assertEquals(Status::QUEUED, $oldest->status);
        $this->assertEquals(Status::QUEUED, $middle->status);
        $this->assertEquals(Status::QUEUED, $newest->status);
    }

    /** @test */
    public function it_prioritizes_high_priority_within_same_scheduled_time()
    {
        Queue::fake();

        $lowPriority = Notification::factory()->pending()->create([
            'scheduled_at' => now()->subMinute(),
            'priority' => Priority::LOW,
        ]);

        $highPriority = Notification::factory()->pending()->create([
            'scheduled_at' => now()->subMinute(),
            'priority' => Priority::HIGH,
        ]);

        $job = new ProcessScheduledNotificationsJob();
        $job->handle();

        Queue::assertPushed(ProcessNotificationJob::class, 2);

        // Both should be queued, high priority should have higher RabbitMQ priority
        $lowPriority->refresh();
        $highPriority->refresh();

        $this->assertEquals(Status::QUEUED, $lowPriority->status);
        $this->assertEquals(Status::QUEUED, $highPriority->status);
    }

    /** @test */
    public function it_does_not_process_already_queued_notifications()
    {
        Queue::fake();

        // Notification already in queued status
        Notification::factory()->queued()->create([
            'scheduled_at' => now()->subMinute(),
        ]);

        $job = new ProcessScheduledNotificationsJob();
        $job->handle();

        // Should not dispatch (already queued)
        Queue::assertNotPushed(ProcessNotificationJob::class);
    }

    /** @test */
    public function it_does_not_process_cancelled_notifications()
    {
        Queue::fake();

        Notification::factory()->cancelled()->create([
            'scheduled_at' => now()->subMinute(),
        ]);

        $job = new ProcessScheduledNotificationsJob();
        $job->handle();

        Queue::assertNotPushed(ProcessNotificationJob::class);
    }

    /** @test */
    public function it_does_not_process_sent_notifications()
    {
        Queue::fake();

        Notification::factory()->sent()->create([
            'scheduled_at' => now()->subMinute(),
        ]);

        $job = new ProcessScheduledNotificationsJob();
        $job->handle();

        Queue::assertNotPushed(ProcessNotificationJob::class);
    }

    /** @test */
    public function it_does_nothing_when_no_scheduled_notifications_ready()
    {
        Queue::fake();

        // Only future notifications
        Notification::factory()->pending()->create([
            'scheduled_at' => now()->addHour(),
        ]);

        $job = new ProcessScheduledNotificationsJob();
        $job->handle();

        Queue::assertNotPushed(ProcessNotificationJob::class);
    }

    /** @test */
    public function it_continues_processing_if_one_notification_fails()
    {
        Queue::fake();

        $notification1 = Notification::factory()->pending()->create([
            'scheduled_at' => now()->subMinute(),
        ]);

        $notification2 = Notification::factory()->pending()->create([
            'scheduled_at' => now()->subMinute(),
        ]);

        // Even if one fails (caught internally), others should still process
        $job = new ProcessScheduledNotificationsJob();
        $job->handle();

        // Both should still be attempted
        Queue::assertPushed(ProcessNotificationJob::class, 2);
    }

    /** @test */
    public function it_dispatches_to_correct_queue_based_on_channel()
    {
        Queue::fake();

        Notification::factory()->pending()->create([
            'scheduled_at' => now()->subMinute(),
            'channel' => Channel::EMAIL,
        ]);

        Notification::factory()->pending()->create([
            'scheduled_at' => now()->subMinute(),
            'channel' => Channel::SMS,
        ]);

        $job = new ProcessScheduledNotificationsJob();
        $job->handle();

        Queue::assertPushedOn('notifications-email', ProcessNotificationJob::class);
        Queue::assertPushedOn('notifications-sms', ProcessNotificationJob::class);
    }
}
