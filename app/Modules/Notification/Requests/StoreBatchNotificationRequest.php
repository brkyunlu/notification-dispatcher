<?php

namespace App\Modules\Notification\Requests;

use App\Shared\Enums\Channel;
use App\Shared\Enums\Priority;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class StoreBatchNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for batch notifications.
     */
    public function rules(): array
    {
        return [
            'notifications' => ['required', 'array', 'min:1', 'max:1000'],
            'notifications.*.recipient' => ['required', 'string'],
            'notifications.*.channel' => ['required', Rule::enum(Channel::class)],
            'notifications.*.content' => ['required_without:notifications.*.template_id', 'string'],
            'notifications.*.subject' => ['nullable', 'string'],
            'notifications.*.priority' => ['nullable', Rule::enum(Priority::class)],
            'notifications.*.template_id' => ['nullable', 'uuid', 'exists:templates,id'],
            'notifications.*.variables' => ['nullable', 'array'],
            'notifications.*.scheduled_at' => ['nullable', 'date', 'after:now'],
            'notifications.*.metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'notifications.required' => 'The notifications array is required.',
            'notifications.min' => 'At least one notification is required.',
            'notifications.max' => 'Maximum 1000 notifications allowed per batch.',
            'notifications.*.recipient.required' => 'Recipient is required for all notifications.',
            'notifications.*.channel.required' => 'Channel is required for all notifications.',
            'notifications.*.content.required_without' => 'Content is required (unless using template_id).',
            'notifications.*.channel.Illuminate\Validation\Rules\Enum' => 'Channel must be one of: sms, email, push.',
            'notifications.*.scheduled_at.after' => 'Scheduled time must be in the future.',
        ];
    }

    /**
     * Handle a failed validation attempt - API standard response
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors()->toArray();
        $simplifiedErrors = [];
        foreach ($errors as $field => $messages) {
            $simplifiedErrors[$field] = is_array($messages) ? $messages[0] : $messages;
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
}
