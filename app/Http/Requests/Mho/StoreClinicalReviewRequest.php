<?php

namespace App\Http\Requests\Mho;

use App\Models\ClinicalEncounter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClinicalReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'mho';
    }

    public function rules(): array
    {
        return [
            'reviewed_at' => ['required', 'date', 'before_or_equal:now'],
            'final_assessment' => ['required', 'string', 'max:5000'],
            'diagnostic_override' => ['nullable', 'string', 'max:5000'],
            'prescription_notes' => ['nullable', 'string', 'max:5000'],
            'referral_destination' => ['nullable', 'string', 'max:255'],
            'final_disposition' => ['required', 'string', 'max:1000'],
            'return_instructions' => ['nullable', 'string', 'max:3000'],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
            'follow_up_date' => ['nullable', 'date'],
            'follow_up_status' => ['nullable', Rule::in([
                ClinicalEncounter::FOLLOW_UP_DUE,
                ClinicalEncounter::FOLLOW_UP_COMPLETED,
                ClinicalEncounter::FOLLOW_UP_MISSED,
                ClinicalEncounter::FOLLOW_UP_RESCHEDULED,
            ])],
            'follow_up_notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->filled('follow_up_status') && ! $this->filled('follow_up_date')
                && in_array($this->input('follow_up_status'), [ClinicalEncounter::FOLLOW_UP_DUE, ClinicalEncounter::FOLLOW_UP_RESCHEDULED], true)
            ) {
                $validator->errors()->add('follow_up_date', 'A follow-up date is required for due or rescheduled municipal cases.');
            }
        });
    }
}
