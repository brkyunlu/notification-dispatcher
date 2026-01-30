<?php

namespace App\Http\Requests;

use App\Enums\Channel;
use App\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Single notification
            'recipient' => 'required_without:notifications|string',
            'channel' => ['required_without:notifications', Rule::enum(Channel::class)],
            'content' => 'required_without:notifications|string',
            'subject' => 'nullable|string',
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'template_id' => 'nullable|uuid|exists:templates,id',
            'scheduled_at' => 'nullable|date|after:now',
            'metadata' => 'nullable|array',
            
            // Batch notifications
            'notifications' => 'required_without:recipient|array|min:1|max:1000',
            'notifications.*.recipient' => 'required|string',
            'notifications.*.channel' => ['required', Rule::enum(Channel::class)],
            'notifications.*.content' => 'required|string',
            'notifications.*.subject' => 'nullable|string',
            'notifications.*.priority' => ['nullable', Rule::enum(Priority::class)],
            'notifications.*.template_id' => 'nullable|uuid|exists:templates,id',
            'notifications.*.scheduled_at' => 'nullable|date|after:now',
            'notifications.*.metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'notifications.max' => 'Batch size cannot exceed 1000 notifications',
            'scheduled_at.after' => 'Scheduled time must be in the future',
            'notifications.*.scheduled_at.after' => 'Scheduled time must be in the future',
        ];
    }

    /**
     * Check if this is a batch request
     */
    public function isBatch(): bool
    {
        return $this->has('notifications');
    }
}
