<?php

namespace App\Http\Requests\Bhw;

use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;

class StoreTriageRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'bhw';
    }

    public function rules(): array
    {
        return [
            'resident_id' => ['required', 'integer', 'exists:residents,id'],
            'measured_at' => ['required', 'date', 'before_or_equal:now'],
            'bp_systolic' => ['nullable', 'integer', 'min:40', 'max:300'],
            'bp_diastolic' => ['nullable', 'integer', 'min:30', 'max:200'],
            'heart_rate' => ['nullable', 'integer', 'min:20', 'max:250'],
            'temperature_celsius' => ['nullable', 'numeric', 'min:30', 'max:45'],
            'respiratory_rate' => ['nullable', 'integer', 'min:5', 'max:80'],
            'blood_glucose_mg_dl' => ['nullable', 'numeric', 'min:20', 'max:600'],
            'triage_notes' => ['nullable', 'string', 'max:1500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $resident = Resident::query()->with('household.purok')->find($this->integer('resident_id'));

            if (! $resident || (int) $resident->household?->purok?->barangay_id !== (int) $this->user()->assigned_barangay_id) {
                $validator->errors()->add('resident_id', 'Select a verified resident from your assigned barangay.');
            }

            if ($resident && ($resident->resident_status !== Resident::STATUS_ACTIVE || ! $resident->is_active)) {
                $validator->errors()->add('resident_id', 'Only active verified residents can receive clinic triage entries.');
            }

            if ($this->filled('bp_systolic') xor $this->filled('bp_diastolic')) {
                $validator->errors()->add('bp_systolic', 'Please provide both blood pressure values together.');
            }

            $hasVitals = collect([
                $this->input('bp_systolic'),
                $this->input('bp_diastolic'),
                $this->input('heart_rate'),
                $this->input('temperature_celsius'),
                $this->input('respiratory_rate'),
                $this->input('blood_glucose_mg_dl'),
                $this->input('triage_notes'),
            ])->contains(fn ($value) => ! is_null($value) && $value !== '');

            if (! $hasVitals) {
                $validator->errors()->add('triage_notes', 'Add at least one vital sign or a brief triage note before submitting.');
            }
        });
    }
}
