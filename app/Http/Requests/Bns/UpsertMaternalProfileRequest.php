<?php

namespace App\Http\Requests\Bns;

use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;

class UpsertMaternalProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'bns';
    }

    public function rules(): array
    {
        return [
            'resident_id' => ['nullable', 'integer', 'exists:residents,id'],
            'is_currently_pregnant' => ['nullable', 'boolean'],
            'is_currently_lactating' => ['nullable', 'boolean'],
            'expected_delivery_date' => ['nullable', 'date'],
            'current_risk_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var Resident|null $resident */
            $resident = $this->route('resident');

            if (! $resident && $this->filled('resident_id')) {
                $resident = Resident::query()->with('household.purok')->find($this->integer('resident_id'));
            }

            if (! $resident) {
                $validator->errors()->add('resident_id', 'Select a verified resident to track.');
                return;
            }

            if ($resident->sex !== 'Female') {
                $validator->errors()->add('resident_id', 'Maternal tracking is currently limited to verified female residents.');
            }

            if ($resident->resident_status !== Resident::STATUS_ACTIVE || ! $resident->is_active) {
                $validator->errors()->add('resident_id', 'Only active verified residents can be tracked here.');
            }

            if ((int) $resident->household?->purok?->barangay_id !== (int) $this->user()->assigned_barangay_id) {
                $validator->errors()->add('resident_id', 'Select a verified resident from your assigned barangay.');
            }
        });
    }
}
