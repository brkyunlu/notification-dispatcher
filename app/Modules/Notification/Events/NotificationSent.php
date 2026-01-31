<?php

namespace App\Modules\Notification\Events;

use App\Modules\Notification\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * NotificationSent Event
 * 
 * Broadcast when a notification is successfully sent
 */
class NotificationSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Notification $notification
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('notifications'),
            new Channel('notification.' . $this->notification->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'recipient' => $this->notification->recipient,
            'channel' => $this->notification->channel->value,
            'status' => $this->notification->status->value,
            'priority' => $this->notification->priority->value,
            'sent_at' => $this->notification->sent_at?->toIso8601String(),
            'external_message_id' => $this->notification->external_message_id,
            'batch_id' => $this->notification->batch_id,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'notification.sent';
    }
}
