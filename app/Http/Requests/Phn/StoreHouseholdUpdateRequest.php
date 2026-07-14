<?php

namespace App\Http\Requests\Phn;

use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;

class StoreHouseholdUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'phn';
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer', 'exists:households,id'],
            'purok_id' => ['required', 'integer', 'exists:puroks,id'],
            'household_no' => ['required', 'string', 'max:50'],
            'household_address' => ['required', 'string', 'max:255'],
            'drinking_water_source' => ['nullable', 'string', 'max:100'],
            'has_sanitary_toilet' => ['nullable', 'boolean'],
            'sanitary_toilet_type' => ['nullable', 'string', 'max:100'],
            'garbage_disposal_method' => ['nullable', 'string', 'in:' . implode(',', array_keys(Household::GARBAGE_DISPOSAL_METHODS))],
            'has_backyard_garden' => ['nullable', 'boolean'],
            'housing_material_type' => ['nullable', 'string', 'in:' . implode(',', array_keys(Household::HOUSING_MATERIAL_TYPES))],
            'head_resident_id' => ['nullable', 'integer', 'exists:residents,id'],
            'is_social_aid_beneficiary' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'request_reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $household = Household::query()->with(['purok', 'residents'])->find($this->integer('subject_id'));
            $purok = Purok::query()->find($this->integer('purok_id'));

            if (! $household) {
                $validator->errors()->add('subject_id', 'Select a verified household from the municipal registry.');
            }

            if (! $purok) {
                $validator->errors()->add('purok_id', 'Select a valid purok from the municipal registry.');
            }

            if ($household && $purok && (int) $household->purok?->barangay_id !== (int) $purok->barangay_id) {
                $validator->errors()->add('purok_id', 'For phase 1, PHN household correction requests must stay inside the household\'s current barangay so they route to the correct Secretary queue.');
            }

            if ($household && $purok) {
                $duplicate = Household::query()
                    ->whereKeyNot($household->id)
                    ->where('purok_id', $purok->id)
                    ->where('household_no', $this->input('household_no'))
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('household_no', 'This household number already exists in the selected purok.');
                }
            }

            if ($household && $this->filled('head_resident_id')) {
                $belongsToHousehold = $household->residents->contains(fn (Resident $resident) => (int) $resident->id === (int) $this->input('head_resident_id'));

                if (! $belongsToHousehold) {
                    $validator->errors()->add('head_resident_id', 'The selected household head must belong to this household.');
                }
            }
        });
    }
}
