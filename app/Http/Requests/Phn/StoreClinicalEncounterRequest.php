<?php

namespace App\Http\Requests\Phn;

use App\Models\Resident;
use App\Models\TriageRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClinicalEncounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'phn';
    }

    public function rules(): array
    {
        return [
            'resident_id' => ['required', 'integer', 'exists:residents,id'],
            'triage_record_id' => ['nullable', 'integer', 'exists:triage_records,id'],
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
            $resident = Resident::query()->with('household.purok')->find($this->integer('resident_id'));
            $triageRecord = $this->filled('triage_record_id')
                ? TriageRecord::query()->with('resident')->find($this->integer('triage_record_id'))
                : null;

            if (! $resident) {
                $validator->errors()->add('resident_id', 'Select a verified resident from the municipal registry.');
            }

            if ($resident && ($resident->resident_status !== Resident::STATUS_ACTIVE || ! $resident->is_active)) {
                $validator->errors()->add('resident_id', 'Only active verified residents can receive PHN clinical encounters.');
            }

            if ($triageRecord) {
                if ((int) $triageRecord->resident_id !== (int) $this->integer('resident_id')) {
                    $validator->errors()->add('triage_record_id', 'The selected triage record must belong to the selected resident.');
                }

                if ($triageRecord->clinicalEncounter()->exists()) {
                    $validator->errors()->add('triage_record_id', 'This triage record is already linked to an existing clinical encounter.');
                }

                if (! is_null($triageRecord->consumed_at) || ! is_null($triageRecord->consumed_by_user_id)) {
                    $validator->errors()->add('triage_record_id', 'This triage record has already been consumed by a clinical reviewer.');
                }

                if ($triageRecord->triage_status !== TriageRecord::STATUS_PENDING) {
                    $validator->errors()->add('triage_record_id', 'Only pending triage records can be consumed into a PHN encounter.');
                }
            }

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
