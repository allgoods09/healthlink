<?php

namespace App\Http\Requests\Secretary;

use App\Models\HouseholdDraft;
use App\Models\Purok;
use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ApproveHouseholdDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()?->role === 'secretary';
    }

    public function rules(): array
    {
        return [
            'purok_id' => [
                'required',
                'integer',
                'exists:puroks,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $purok = Purok::query()->find($value);

                    if ($purok && (int) $purok->barangay_id !== (int) Auth::user()?->assigned_barangay_id) {
                        $fail('The selected purok is outside your assigned barangay.');
                    }
                },
            ],
            'household_no' => [
                'required',
                'string',
                'max:50',
                Rule::unique('households')->where(fn ($query) => $query->where('purok_id', $this->input('purok_id'))),
            ],
            'household_address' => ['required', 'string'],
            'drinking_water_source' => ['nullable', 'string', 'max:100'],
            'has_sanitary_toilet' => ['nullable', 'boolean'],
            'sanitary_toilet_type' => ['nullable', 'string', 'max:100'],
            'garbage_disposal_method' => ['nullable', 'string', 'in:' . implode(',', array_keys(\App\Models\Household::GARBAGE_DISPOSAL_METHODS))],
            'has_backyard_garden' => ['nullable', 'boolean'],
            'housing_material_type' => ['nullable', 'string', 'in:' . implode(',', array_keys(\App\Models\Household::HOUSING_MATERIAL_TYPES))],
            'is_social_aid_beneficiary' => ['nullable', 'boolean'],
            'head_draft_id' => ['nullable', 'integer'],
            'verification_notes' => ['nullable', 'string', 'max:2000'],
            'residents' => ['required', 'array', 'min:1'],
            'residents.*.draft_id' => ['required', 'integer'],
            'residents.*.philsys_card_no' => ['nullable', 'string', 'max:50', 'unique:residents,philsys_card_no'],
            'residents.*.last_name' => ['required', 'string', 'max:100'],
            'residents.*.first_name' => ['required', 'string', 'max:100'],
            'residents.*.middle_name' => ['nullable', 'string', 'max:100'],
            'residents.*.suffix' => ['nullable', 'string', 'max:20'],
            'residents.*.birth_date' => ['required', 'date', 'before:today'],
            'residents.*.birth_place' => ['required', 'string', 'max:255'],
            'residents.*.sex' => ['required', 'in:Male,Female'],
            'residents.*.civil_status' => ['required', 'string', 'max:50'],
            'residents.*.citizenship' => ['required', 'string', 'max:100'],
            'residents.*.religion' => ['nullable', 'string', 'max:100'],
            'residents.*.contact_number' => ['nullable', 'string', 'max:20'],
            'residents.*.email_address' => ['nullable', 'email', 'max:100'],
            'residents.*.relationship_to_head' => ['required', 'string', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var HouseholdDraft|null $householdDraft */
            $householdDraft = $this->route('householdDraft');

            if (! $householdDraft) {
                return;
            }

            $validDraftIds = $householdDraft->residentDrafts()->pluck('id')->all();
            $submittedDraftIds = collect($this->input('residents', []))
                ->pluck('draft_id')
                ->map(fn ($value) => (int) $value)
                ->all();

            sort($validDraftIds);
            sort($submittedDraftIds);

            if ($validDraftIds !== $submittedDraftIds) {
                $validator->errors()->add('residents', 'Every resident draft in this package must be reviewed before approval.');
            }

            $headDraftId = $this->input('head_draft_id');

            if ($headDraftId && ! in_array((int) $headDraftId, $validDraftIds, true)) {
                $validator->errors()->add('head_draft_id', 'The selected household head must belong to this draft package.');
            }

            $seenPayloadResidents = [];

            foreach ($this->input('residents', []) as $index => $residentPayload) {
                $identityKey = strtolower(trim(($residentPayload['first_name'] ?? '').'|'.($residentPayload['last_name'] ?? '').'|'.($residentPayload['birth_date'] ?? '')));

                if (isset($seenPayloadResidents[$identityKey])) {
                    $validator->errors()->add("residents.{$index}.birth_date", 'Duplicate resident entries were found in this draft package.');
                    continue;
                }

                $seenPayloadResidents[$identityKey] = true;

                $duplicate = Resident::query()
                    ->whereRaw('LOWER(first_name) = ?', [strtolower((string) ($residentPayload['first_name'] ?? ''))])
                    ->whereRaw('LOWER(last_name) = ?', [strtolower((string) ($residentPayload['last_name'] ?? ''))])
                    ->whereDate('birth_date', (string) ($residentPayload['birth_date'] ?? ''))
                    ->whereHas('household', function ($query): void {
                        $query->where('purok_id', $this->input('purok_id'));
                    })
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add("residents.{$index}.birth_date", 'A resident with the same name and birth date already exists in the selected purok.');
                }
            }
        });
    }
}
