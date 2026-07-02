<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class RateLimitUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::user()->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rate_limit_attempts' => ['required', 'integer', 'min:1', 'max:1000'],
            'rate_limit_decay_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'sync_batch_size' => ['required', 'integer', 'min:10', 'max:500'],
            'backup_retention_days' => ['required', 'integer', 'min:1', 'max:365'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'rate_limit_attempts.required' => 'Rate limit attempts is required.',
            'rate_limit_attempts.min' => 'Rate limit attempts must be at least 1.',
            'rate_limit_decay_minutes.required' => 'Rate limit decay minutes is required.',
            'rate_limit_decay_minutes.min' => 'Rate limit decay minutes must be at least 1.',
            'sync_batch_size.required' => 'Sync batch size is required.',
            'sync_batch_size.min' => 'Sync batch size must be at least 10.',
            'backup_retention_days.required' => 'Backup retention days is required.',
            'backup_retention_days.min' => 'Backup retention days must be at least 1.',
        ];
    }
}