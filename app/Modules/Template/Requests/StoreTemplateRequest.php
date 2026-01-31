<?php

namespace App\Modules\Template\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTemplateRequest extends FormRequest
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
        $slugRule = 'unique:templates,slug';
        
        // If updating, exclude current template from unique check
        if ($this->route('id')) {
            $slugRule .= ',' . $this->route('id');
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', $slugRule],
            'channel' => ['required', Rule::in(['sms', 'email', 'push'])],
            'content' => ['required', 'string'],
            'subject' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Template name is required.',
            'channel.required' => 'Channel is required.',
            'channel.in' => 'Channel must be one of: sms, email, push.',
            'content.required' => 'Template content is required.',
        ];
    }
}
