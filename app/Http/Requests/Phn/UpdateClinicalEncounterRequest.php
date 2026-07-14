<?php

namespace App\Http\Requests\Phn;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClinicalEncounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'phn';
    }

    public function rules(): array
    {
        return [
            'encountered_at' => ['required', 'date', 'before_or_equal:now'],
            'consultation_notes' => ['nullable', 'string', 'max:5000'],
            'working_impression' => ['nullable', 'string', 'max:3000'],
            'action_taken' => ['nullable', 'string', 'max:3000'],
            'disposition' => ['nullable', 'string', 'max:1000'],
            'follow_up_date' => ['nullable', 'date'],
            'follow_up_status' => ['nullable', Rule::in(['due', 'completed', 'missed', 'rescheduled'])],
            'follow_up_notes' => ['nullable', 'string', 'max:3000'],
            'medicines_administered' => ['nullable', 'string', 'max:3000'],
            'lifestyle_advice' => ['nullable', 'string', 'max:3000'],
            'referral_notes' => ['nullable', 'string', 'max:3000'],
            'return_instructions' => ['nullable', 'string', 'max:3000'],
            'is_escalated_to_mho' => ['nullable', 'boolean'],
            'escalation_notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->filled('follow_up_status') && ! $this->filled('follow_up_date')) {
                $validator->errors()->add('follow_up_date', 'Please set a follow-up date when selecting a follow-up status.');
            }

            $hasClinicalPayload = collect([
                $this->input('consultation_notes'),
                $this->input('working_impression'),
                $this->input('action_taken'),
                $this->input('disposition'),
                $this->input('medicines_administered'),
                $this->input('lifestyle_advice'),
                $this->input('referral_notes'),
                $this->input('return_instructions'),
            ])->contains(fn ($value) => ! is_null($value) && trim((string) $value) !== '');

            if (! $hasClinicalPayload) {
                $validator->errors()->add('consultation_notes', 'Please capture at least one clinical note, assessment, action, or treatment detail.');
            }

            if ($this->boolean('is_escalated_to_mho') && ! $this->filled('escalation_notes') && ! $this->filled('referral_notes')) {
                $validator->errors()->add('escalation_notes', 'Please explain why this case is being escalated to the MHO.');
            }
        });
    }
}
