<?php

namespace App\Http\Requests\Secretary;

use App\Models\Household;
use App\Models\ProfileUpdateRequest;
use App\Models\Purok;
use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ApplyProfileUpdateRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()?->role === 'secretary';
    }

    public function rules(): array
    {
        /** @var ProfileUpdateRequest $profileUpdateRequest */
        $profileUpdateRequest = $this->route('profileUpdateRequest');

        return match ($profileUpdateRequest->subject_type) {
            ProfileUpdateRequest::SUBJECT_RESIDENT => $this->residentRules($profileUpdateRequest),
            ProfileUpdateRequest::SUBJECT_HOUSEHOLD => $this->householdRules($profileUpdateRequest),
            default => [],
        };
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var ProfileUpdateRequest $profileUpdateRequest */
            $profileUpdateRequest = $this->route('profileUpdateRequest');

            if ($profileUpdateRequest->subject_type === ProfileUpdateRequest::SUBJECT_RESIDENT) {
                /** @var Resident|null $resident */
                $resident = Resident::query()->find($profileUpdateRequest->subject_id);
                $household = Household::query()->find($this->input('household_id'));

                if (! $resident || ! $household) {
                    return;
                }

                $duplicate = Resident::query()
                    ->whereKeyNot($resident->id)
                    ->whereRaw('LOWER(first_name) = ?', [strtolower((string) $this->input('first_name'))])
                    ->whereRaw('LOWER(last_name) = ?', [strtolower((string) $this->input('last_name'))])
                    ->whereDate('birth_date', $this->input('birth_date'))
                    ->whereHas('household', function ($query) use ($household): void {
                        $query->where('purok_id', $household->purok_id);
                    })
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('birth_date', 'A resident with the same name and birth date already exists in the selected purok.');
                }

                $status = $this->input('resident_status', Resident::STATUS_ACTIVE);

                if ($status === Resident::STATUS_DECEASED && ! $this->filled('date_of_death')) {
                    $validator->errors()->add('date_of_death', 'Please provide the date of death for deceased residents.');
                }

                if ($status === Resident::STATUS_RELOCATED && ! $this->filled('moved_out_at')) {
                    $validator->errors()->add('moved_out_at', 'Please provide the move-out date for relocated residents.');
                }
            }

            if ($profileUpdateRequest->subject_type === ProfileUpdateRequest::SUBJECT_HOUSEHOLD) {
                /** @var Household|null $household */
                $household = Household::query()->find($profileUpdateRequest->subject_id);

                if (! $household || ! $this->filled('head_resident_id')) {
                    return;
                }

                $belongsToHousehold = Resident::query()
                    ->whereKey($this->input('head_resident_id'))
                    ->where('household_id', $household->id)
                    ->exists();

                if (! $belongsToHousehold) {
                    $validator->errors()->add('head_resident_id', 'The selected household head must belong to this household.');
                }
            }
        });
    }

    private function residentRules(ProfileUpdateRequest $profileUpdateRequest): array
    {
        return [
            'household_id' => [
                'required',
                'exists:households,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $household = Household::query()->with('purok')->find($value);

                    if (! $household) {
                        return;
                    }

                    if ((int) $household->purok?->barangay_id !== (int) Auth::user()?->assigned_barangay_id) {
                        $fail('You can only assign residents to households in your assigned barangay.');
                    }
                },
            ],
            'philsys_card_no' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('residents', 'philsys_card_no')->ignore($profileUpdateRequest->subject_id),
            ],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['required', 'date', 'before:today'],
            'birth_place' => ['required', 'string', 'max:255'],
            'sex' => ['required', 'in:Male,Female'],
            'civil_status' => ['required', 'string', 'max:50'],
            'citizenship' => ['required', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'email_address' => ['nullable', 'email', 'max:100'],
            'relationship_to_head' => ['required', 'string', 'max:100'],
            'resident_status' => ['required', 'in:active,deceased,relocated'],
            'moved_in_at' => ['nullable', 'date'],
            'moved_out_at' => ['nullable', 'date'],
            'date_of_death' => ['nullable', 'date'],
            'status_notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function householdRules(ProfileUpdateRequest $profileUpdateRequest): array
    {
        return [
            'purok_id' => [
                'required',
                'exists:puroks,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $purok = Purok::query()->find($value);

                    if ($purok && (int) $purok->barangay_id !== (int) Auth::user()?->assigned_barangay_id) {
                        $fail('You can only update households inside your assigned barangay.');
                    }
                },
            ],
            'household_no' => [
                'required',
                'string',
                'max:50',
                Rule::unique('households')->where(fn ($query) => $query->where('purok_id', $this->input('purok_id')))->ignore($profileUpdateRequest->subject_id),
            ],
            'household_address' => ['required', 'string'],
            'drinking_water_source' => ['nullable', 'string', 'max:100'],
            'has_sanitary_toilet' => ['nullable', 'boolean'],
            'sanitary_toilet_type' => ['nullable', 'string', 'max:100'],
            'garbage_disposal_method' => ['nullable', 'string', 'in:' . implode(',', array_keys(\App\Models\Household::GARBAGE_DISPOSAL_METHODS))],
            'has_backyard_garden' => ['nullable', 'boolean'],
            'housing_material_type' => ['nullable', 'string', 'in:' . implode(',', array_keys(\App\Models\Household::HOUSING_MATERIAL_TYPES))],
            'head_resident_id' => ['nullable', 'exists:residents,id'],
            'is_social_aid_beneficiary' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
