<?php

namespace App\Modules\Notification\Requests;

use App\Shared\Enums\Channel;
use App\Shared\Enums\Priority;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
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
            // Single notification
            'recipient.required_without' => 'Recipient is required',
            'channel.required_without' => 'Channel is required',
            'content.required_without' => 'Content is required',
            'channel.Illuminate\Validation\Rules\Enum' => 'Channel must be one of: sms, email, push',
            'priority.Illuminate\Validation\Rules\Enum' => 'Priority must be one of: low, normal, high',
            'scheduled_at.after' => 'Scheduled time must be in the future',
            'template_id.exists' => 'Template not found',
            
            // Batch notifications
            'notifications.required_without' => 'Either provide notification fields or notifications array',
            'notifications.array' => 'Notifications must be an array',
            'notifications.min' => 'At least one notification is required',
            'notifications.max' => 'Maximum 1000 notifications allowed per batch',
            'notifications.*.recipient.required' => 'Recipient is required for all notifications',
            'notifications.*.channel.required' => 'Channel is required for all notifications',
            'notifications.*.content.required' => 'Content is required for all notifications',
            'notifications.*.channel.Illuminate\Validation\Rules\Enum' => 'Channel must be one of: sms, email, push',
            'notifications.*.scheduled_at.after' => 'Scheduled time must be in the future',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'notifications.*.recipient' => 'recipient',
            'notifications.*.channel' => 'channel',
            'notifications.*.content' => 'content',
            'notifications.*.subject' => 'subject',
            'notifications.*.priority' => 'priority',
        ];
    }

    /**
     * Handle a failed validation attempt - API standard response
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors()->toArray();
        
        // Simplify error messages
        $simplifiedErrors = [];
        foreach ($errors as $field => $messages) {
            $simplifiedErrors[$field] = $messages[0]; // Take first message only
        }

        throw new HttpResponseException(
            response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid',
                    'details' => $simplifiedErrors,
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY)
        );
    }

    /**
     * Check if this is a batch request
     */
    public function isBatch(): bool
    {
        return $this->has('notifications');
    }
}
