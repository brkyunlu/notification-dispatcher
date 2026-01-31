<?php

namespace App\Modules\Notification\Events;

use App\Modules\Notification\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * NotificationFailed Event
 * 
 * Broadcast when a notification fails permanently
 */
class NotificationFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Notification $notification,
        public string $error
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
            'attempts' => $this->notification->attempts,
            'last_error' => $this->notification->last_error,
            'error' => $this->error,
            'batch_id' => $this->notification->batch_id,
            'subject' => $this->notification->subject,
            'content' => $this->notification->content,
            'created_at' => $this->notification->created_at?->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'notification.failed';
    }
}
