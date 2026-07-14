<?php

namespace App\Http\Requests\Bhw;

use App\Models\Household;
use App\Models\Purok;
use Illuminate\Foundation\Http\FormRequest;

class StoreHouseholdDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'bhw';
    }

    public function rules(): array
    {
        return [
            'purok_id' => ['required', 'integer', 'exists:puroks,id'],
            'household_address' => ['required', 'string', 'max:255'],
            'drinking_water_source' => ['nullable', 'string', 'max:100'],
            'has_sanitary_toilet' => ['nullable', 'boolean'],
            'sanitary_toilet_type' => ['nullable', 'string', 'max:100'],
            'garbage_disposal_method' => ['nullable', 'string', 'in:' . implode(',', array_keys(Household::GARBAGE_DISPOSAL_METHODS))],
            'has_backyard_garden' => ['nullable', 'boolean'],
            'housing_material_type' => ['nullable', 'string', 'in:' . implode(',', array_keys(Household::HOUSING_MATERIAL_TYPES))],
            'is_social_aid_beneficiary' => ['nullable', 'boolean'],
            'residents' => ['required', 'array', 'min:1'],
            'residents.*.philsys_card_no' => ['nullable', 'string', 'max:50'],
            'residents.*.last_name' => ['required', 'string', 'max:100'],
            'residents.*.first_name' => ['required', 'string', 'max:100'],
            'residents.*.middle_name' => ['nullable', 'string', 'max:100'],
            'residents.*.suffix' => ['nullable', 'string', 'max:20'],
            'residents.*.birth_date' => ['required', 'date', 'before_or_equal:today'],
            'residents.*.birth_place' => ['required', 'string', 'max:255'],
            'residents.*.sex' => ['required', 'in:Male,Female'],
            'residents.*.civil_status' => ['required', 'string', 'max:50'],
            'residents.*.citizenship' => ['required', 'string', 'max:100'],
            'residents.*.religion' => ['nullable', 'string', 'max:100'],
            'residents.*.contact_number' => ['nullable', 'string', 'max:20'],
            'residents.*.email_address' => ['nullable', 'email', 'max:100'],
            'residents.*.relationship_to_head' => ['required', 'string', 'max:100'],
            'residents.*.is_household_head_candidate' => ['nullable', 'boolean'],
            'residents.*.draft_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $purok = Purok::query()->find($this->integer('purok_id'));

            if ($purok && (int) $purok->barangay_id !== (int) $this->user()->assigned_barangay_id) {
                $validator->errors()->add('purok_id', 'Select a purok inside your assigned barangay.');
            }

            $seenResidents = [];
            $headCandidates = 0;

            foreach ($this->input('residents', []) as $index => $residentPayload) {
                $identityKey = strtolower(trim(($residentPayload['first_name'] ?? '').'|'.($residentPayload['last_name'] ?? '').'|'.($residentPayload['birth_date'] ?? '')));

                if (isset($seenResidents[$identityKey])) {
                    $validator->errors()->add("residents.{$index}.birth_date", 'Duplicate resident entries were found in this draft package.');
                }

                $seenResidents[$identityKey] = true;

                if (! empty($residentPayload['is_household_head_candidate'])) {
                    $headCandidates++;
                }
            }

            if ($headCandidates > 1) {
                $validator->errors()->add('residents', 'Only one resident can be marked as the suggested household head.');
            }
        });
    }
}
