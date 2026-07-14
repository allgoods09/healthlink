<?php

namespace App\Http\Requests\Admin\Geometry;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class HouseholdStoreRequest extends FormRequest
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
            'purok_id' => [
                'required',
                'exists:puroks,id',
                function ($attribute, $value, $fail) {
                    $user = Auth::user();
                    
                    // BHW can only create households in their assigned purok
                    if ($user->role === 'bhw' && $user->assigned_purok_id != $value) {
                        $fail('You can only create households in your assigned purok.');
                    }
                    
                    // Secretary and BNS can only create households in their assigned barangay
                    if (in_array($user->role, ['secretary', 'bns'])) {
                        $purok = \App\Models\Purok::find($value);
                        if ($purok && $purok->barangay_id != $user->assigned_barangay_id) {
                            $fail('You can only create households in your assigned barangay.');
                        }
                    }
                }
            ],
            'household_no' => [
                'required',
                'string',
                'max:50',
                Rule::unique('households')->where(function ($query) {
                    return $query->where('purok_id', $this->input('purok_id'));
                })
            ],
            'household_address' => ['required', 'string'],
            'head_resident_id' => ['nullable'],
            'is_social_aid_beneficiary' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->filled('head_resident_id')) {
                $validator->errors()->add('head_resident_id', 'Select a household head after the household has residents.');
            }
        });
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'purok_id.required' => 'Please select a purok.',
            'purok_id.exists' => 'The selected purok does not exist.',
            'household_no.required' => 'The household number is required.',
            'household_no.unique' => 'This household number already exists in the selected purok.',
            'household_address.required' => 'The household address is required.',
        ];
    }
}
