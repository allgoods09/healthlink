<?php

namespace App\Http\Requests\Bns;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFeedingProgramEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'bns';
    }

    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
            'completion_notes' => ['nullable', 'string', 'max:1500'],
        ];
    }
}
