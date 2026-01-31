<?php

namespace App\Modules\Notification\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_id' => $this->batch_id,
            'recipient' => $this->recipient,
            'channel' => $this->channel?->value,
            'content' => $this->content,
            'subject' => $this->subject,
            'priority' => $this->priority?->value ?? 'normal',
            'status' => $this->status?->value ?? 'pending',
            'template_id' => $this->template_id,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'attempts' => $this->attempts,
            'last_error' => $this->last_error,
            'external_message_id' => $this->external_message_id,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
