<?php

namespace App\Http\Requests\Admin\Geometry;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class ResidentUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();
        
        // Allow admin, mho, phn, secretary, bns, bhw to update
        return in_array($user->role, ['admin', 'mho', 'phn', 'secretary', 'bns', 'bhw']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $residentId = $this->route('resident') ? $this->route('resident')->id : null;

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
                    
                    if ($user->role === 'bhw' && $user->assigned_purok_id != $household->purok_id) {
                        $fail('You can only update residents in your assigned purok.');
                    }
                    
                    if (in_array($user->role, ['secretary', 'bns'])) {
                        if ($household->purok->barangay_id != $user->assigned_barangay_id) {
                            $fail('You can only update residents in your assigned barangay.');
                        }
                    }
                }
            ],
            'philsys_card_no' => [
                'nullable', 
                'string', 
                'max:50', 
                Rule::unique('residents')->ignore($residentId)
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
            'is_active' => ['boolean'],
        ];
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
        ];
    }
}