<?php

namespace App\Http\Requests\Phn;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFollowUpStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'phn';
    }

    public function rules(): array
    {
        return [
            'follow_up_date' => ['nullable', 'date'],
            'follow_up_status' => ['required', Rule::in(['due', 'completed', 'missed', 'rescheduled'])],
            'follow_up_notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (($this->input('follow_up_status') === 'due' || $this->input('follow_up_status') === 'rescheduled') && ! $this->filled('follow_up_date')) {
                $validator->errors()->add('follow_up_date', 'A follow-up date is required for due or rescheduled cases.');
            }
        });
    }
}
