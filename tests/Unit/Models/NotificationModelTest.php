<?php

namespace Tests\Unit\Models;

use App\Modules\Notification\Events\NotificationFailed;
use App\Modules\Notification\Events\NotificationQueued;
use App\Modules\Notification\Events\NotificationSent;
use App\Modules\Notification\Models\Notification;
use App\Shared\Enums\Channel;
use App\Shared\Enums\Priority;
use App\Shared\Enums\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Unit tests for Notification model (markAs*, scopes, canRetry, incrementAttempts).
 */
class NotificationModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_marks_as_queued_and_dispatches_event()
    {
        Event::fake([NotificationQueued::class]);

        $notification = Notification::factory()->create(['status' => Status::PENDING]);

        $notification->markAsQueued();

        $notification->refresh();
        $this->assertEquals(Status::QUEUED, $notification->status);
        Event::assertDispatched(NotificationQueued::class);
    }

    /** @test */
    public function it_marks_as_processing()
    {
        $notification = Notification::factory()->create(['status' => Status::QUEUED]);

        $notification->markAsProcessing();

        $notification->refresh();
        $this->assertEquals(Status::PROCESSING, $notification->status);
    }

    /** @test */
    public function it_marks_as_sent_and_dispatches_event()
    {
        Event::fake([NotificationSent::class]);

        $notification = Notification::factory()->create(['status' => Status::PROCESSING]);
        $externalId = 'ext-123';

        $notification->markAsSent($externalId);

        $notification->refresh();
        $this->assertEquals(Status::SENT, $notification->status);
        $this->assertEquals($externalId, $notification->external_message_id);
        $this->assertNotNull($notification->sent_at);
        Event::assertDispatched(NotificationSent::class);
    }

    /** @test */
    public function it_marks_as_failed_and_dispatches_event()
    {
        Event::fake([NotificationFailed::class]);

        $notification = Notification::factory()->create(['status' => Status::PROCESSING]);
        $error = 'Connection timeout';

        $notification->markAsFailed($error);

        $notification->refresh();
        $this->assertEquals(Status::FAILED, $notification->status);
        $this->assertEquals($error, $notification->last_error);
        Event::assertDispatched(NotificationFailed::class);
    }

    /** @test */
    public function it_marks_as_delivered()
    {
        $notification = Notification::factory()->create(['status' => Status::SENT]);

        $notification->markAsDelivered();

        $notification->refresh();
        $this->assertEquals(Status::DELIVERED, $notification->status);
    }

    /** @test */
    public function it_marks_as_cancelled()
    {
        $notification = Notification::factory()->create(['status' => Status::PENDING]);

        $notification->markAsCancelled();

        $notification->refresh();
        $this->assertEquals(Status::CANCELLED, $notification->status);
    }

    /** @test */
    public function it_increments_attempts()
    {
        $notification = Notification::factory()->create(['attempts' => 2]);

        $notification->incrementAttempts();

        $notification->refresh();
        $this->assertEquals(3, $notification->attempts);
    }

    /** @test */
    public function it_can_retry_when_under_max_attempts_and_not_final()
    {
        $notification = Notification::factory()->create([
            'attempts' => 2,
            'status' => Status::FAILED,
        ]);

        $this->assertTrue($notification->canRetry());
    }

    /** @test */
    public function it_cannot_retry_when_max_attempts_reached()
    {
        config(['notification.retry.max_attempts' => 3]);
        $notification = Notification::factory()->create([
            'attempts' => 3,
            'status' => Status::FAILED,
        ]);

        $this->assertFalse($notification->canRetry());
    }

    /** @test */
    public function it_cannot_retry_when_cancelled()
    {
        $notification = Notification::factory()->create([
            'attempts' => 1,
            'status' => Status::CANCELLED,
        ]);

        $this->assertFalse($notification->canRetry());
    }

    /** @test */
    public function it_cannot_retry_when_delivered()
    {
        $notification = Notification::factory()->create([
            'attempts' => 1,
            'status' => Status::DELIVERED,
        ]);

        $this->assertFalse($notification->canRetry());
    }

    /** @test */
    public function it_scope_scheduled_ready_returns_pending_with_scheduled_at_past()
    {
        $past = Notification::factory()->create([
            'scheduled_at' => now()->subHour(),
            'status' => Status::PENDING,
        ]);
        $future = Notification::factory()->create([
            'scheduled_at' => now()->addHour(),
            'status' => Status::PENDING,
        ]);

        $results = Notification::scheduledReady()->get();

        $this->assertTrue($results->contains($past));
        $this->assertFalse($results->contains($future));
    }

    /** @test */
    public function it_scope_by_channel_filters_by_channel()
    {
        $email = Notification::factory()->email()->create();
        Notification::factory()->create(['channel' => Channel::SMS]);

        $results = Notification::byChannel(Channel::EMAIL)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($email->id, $results->first()->id);
    }

    /** @test */
    public function it_scope_by_batch_filters_by_batch_id()
    {
        $batchId = \Illuminate\Support\Str::uuid()->toString();
        $n1 = Notification::factory()->withBatch($batchId)->create();
        $n2 = Notification::factory()->withBatch($batchId)->create();
        Notification::factory()->create(['batch_id' => null]);

        $results = Notification::byBatch($batchId)->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains($n1));
        $this->assertTrue($results->contains($n2));
    }

    /** @test */
    public function it_masked_recipient_attribute_masks_email()
    {
        $notification = Notification::factory()->email()->create(['recipient' => 'john.doe@example.com']);

        $this->assertStringContainsString('*', $notification->masked_recipient);
        $this->assertStringEndsWith('@example.com', $notification->masked_recipient);
    }
}
