<?php

namespace App\Http\Requests\Admin\Geometry;

use App\Models\Household;
use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ResidentStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();
        
        // Allow admin, mho, phn, secretary, bns, bhw to create
        return in_array($user->role, ['admin', 'mho', 'phn', 'secretary', 'bns', 'bhw']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'household_id' => [
                'required',
                'exists:households,id',
                function ($attribute, $value, $fail) {
                    $user = Auth::user();
                    $household = \App\Models\Household::with('purok')->find($value);
                    
                    if (!$household) {
                        return;
                    }
                    
                    // BHW can only add residents to households in their purok
                    if ($user->role === 'bhw' && $user->assigned_purok_id != $household->purok_id) {
                        $fail('You can only add residents to households in your assigned purok.');
                    }
                    
                    // Secretary and BNS can only add residents in their barangay
                    if (in_array($user->role, ['secretary', 'bns'])) {
                        if ($household->purok->barangay_id != $user->assigned_barangay_id) {
                            $fail('You can only add residents in your assigned barangay.');
                        }
                    }
                }
            ],
            'philsys_card_no' => ['nullable', 'string', 'max:50', 'unique:residents'],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['required', 'date', 'before:today'],
            'occupation' => ['nullable', 'string', 'max:150'],
            'employment_status' => ['nullable', 'in:Employed,Unemployed,N/A'],
            'highest_education_level' => ['nullable', 'in:None,Elementary,High School,College,Post Grad,Vocational'],
            'education_status' => ['nullable', 'in:Graduate,Undergraduate,N/A'],
            'is_pwd' => ['boolean'],
            'disability_type' => ['nullable', 'string', 'max:150'],
            'is_ofw' => ['boolean'],
            'is_solo_parent' => ['boolean'],
            'is_osy' => ['boolean'],
            'is_osc' => ['boolean'],
            'is_ip' => ['boolean'],
            'ethnicity' => ['nullable', 'string', 'max:100'],
            'birth_place' => ['required', 'string', 'max:255'],
            'sex' => ['required', 'in:Male,Female'],
            'civil_status' => ['required', 'string', 'max:50'],
            'citizenship' => ['required', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'email_address' => ['nullable', 'email', 'max:100'],
            'relationship_to_head' => ['required', 'string', 'max:100'],
            'resident_status' => ['nullable', 'in:active,deceased,relocated'],
            'moved_in_at' => ['nullable', 'date'],
            'moved_out_at' => ['nullable', 'date'],
            'date_of_death' => ['nullable', 'date'],
            'status_notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $household = Household::find($this->input('household_id'));

            if (! $household) {
                return;
            }

            $duplicate = Resident::query()
                ->whereRaw('LOWER(first_name) = ?', [strtolower((string) $this->input('first_name'))])
                ->whereRaw('LOWER(last_name) = ?', [strtolower((string) $this->input('last_name'))])
                ->whereDate('birth_date', $this->input('birth_date'))
                ->whereHas('household', function ($query) use ($household): void {
                    $query->where('purok_id', $household->purok_id);
                })
                ->exists();

            if ($duplicate) {
                $validator->errors()->add(
                    'birth_date',
                    'A resident with the same name and birth date already exists in the selected purok.'
                );
            }

            $status = $this->input('resident_status', Resident::STATUS_ACTIVE);

            if ($status === Resident::STATUS_DECEASED && ! $this->filled('date_of_death')) {
                $validator->errors()->add('date_of_death', 'Please provide the date of death for deceased residents.');
            }

            if ($status === Resident::STATUS_RELOCATED && ! $this->filled('moved_out_at')) {
                $validator->errors()->add('moved_out_at', 'Please provide the move-out date for relocated residents.');
            }
        });
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'household_id.required' => 'Please select a household.',
            'household_id.exists' => 'The selected household does not exist.',
            'philsys_card_no.unique' => 'This PhilSys ID is already registered.',
            'last_name.required' => 'The last name is required.',
            'first_name.required' => 'The first name is required.',
            'birth_date.required' => 'The birth date is required.',
            'birth_date.before' => 'Birth date must be in the past.',
            'sex.required' => 'Please select a sex.',
            'sex.in' => 'Invalid sex selected.',
            'relationship_to_head.required' => 'Please specify the relationship to the household head.',
            'status_notes.max' => 'Status notes may not exceed 2000 characters.',
        ];
    }
}
