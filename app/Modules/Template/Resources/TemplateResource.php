<?php

namespace App\Modules\Template\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'channel' => $this->channel->value,
            'content' => $this->content,
            'subject' => $this->subject,
            'variables' => $this->variables,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'usage_count' => $this->when(
                $request->query('include_usage_count'),
                fn() => $this->notifications()->count()
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
