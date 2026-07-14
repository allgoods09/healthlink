<?php

namespace App\Http\Requests\Bhw;

use App\Models\Household;
use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResidentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'bhw';
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer', 'exists:residents,id'],
            'household_id' => ['required', 'integer', 'exists:households,id'],
            'philsys_card_no' => ['nullable', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'birth_place' => ['required', 'string', 'max:255'],
            'sex' => ['required', 'in:Male,Female'],
            'civil_status' => ['required', 'string', 'max:50'],
            'citizenship' => ['required', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'email_address' => ['nullable', 'email', 'max:100'],
            'relationship_to_head' => ['required', 'string', 'max:100'],
            'resident_status' => ['required', Rule::in([Resident::STATUS_ACTIVE, Resident::STATUS_DECEASED, Resident::STATUS_RELOCATED])],
            'moved_in_at' => ['nullable', 'date'],
            'moved_out_at' => ['nullable', 'date'],
            'date_of_death' => ['nullable', 'date'],
            'status_notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'request_reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $resident = Resident::query()->with('household.purok')->find($this->integer('subject_id'));
            $household = Household::query()->with('purok')->find($this->integer('household_id'));

            if (! $resident || (int) $resident->household?->purok?->barangay_id !== (int) $this->user()->assigned_barangay_id) {
                $validator->errors()->add('subject_id', 'Select a verified resident from your assigned barangay.');
            }

            if (! $household || (int) $household->purok?->barangay_id !== (int) $this->user()->assigned_barangay_id) {
                $validator->errors()->add('household_id', 'Select a verified household from your assigned barangay.');
            }

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

            if ($this->input('resident_status') === Resident::STATUS_DECEASED && ! $this->filled('date_of_death')) {
                $validator->errors()->add('date_of_death', 'Please provide the date of death for deceased residents.');
            }

            if ($this->input('resident_status') === Resident::STATUS_RELOCATED && ! $this->filled('moved_out_at')) {
                $validator->errors()->add('moved_out_at', 'Please provide the move-out date for relocated residents.');
            }
        });
    }
}
