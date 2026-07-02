<?php

namespace App\Http\Requests\Admin\IAM;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UserUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::user()->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('user') ? $this->route('user')->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 
                'string', 
                'email', 
                'max:255',
                Rule::unique('users')->ignore($userId)
            ],
            'role' => ['required', 'string', Rule::in(['admin', 'mho', 'phn', 'secretary', 'bns', 'bhw'])],
            'assigned_barangay_id' => [
                'nullable',
                'exists:barangays,id',
                function ($attribute, $value, $fail) {
                    $role = $this->input('role');
                    
                    if (in_array($role, ['admin', 'mho', 'phn']) && !is_null($value)) {
                        $fail('Global roles (Admin, MHO, PHN) cannot be assigned to a specific barangay.');
                    }
                    
                    if (in_array($role, ['secretary', 'bns', 'bhw']) && is_null($value)) {
                        $fail('Local roles (Secretary, BNS, BHW) must be assigned to a barangay.');
                    }
                }
            ],
            'assigned_purok_id' => [
                'nullable',
                'exists:puroks,id',
                function ($attribute, $value, $fail) {
                    $role = $this->input('role');
                    $barangayId = $this->input('assigned_barangay_id');
                    
                    if ($role !== 'bhw' && !is_null($value)) {
                        $fail('Only BHWs can be assigned to a specific purok.');
                    }
                    
                    if ($role === 'bhw' && is_null($value)) {
                        $fail('BHWs must be assigned to a specific purok.');
                    }
                    
                    if ($value && $barangayId) {
                        $purok = \App\Models\Purok::find($value);
                        if ($purok && $purok->barangay_id != $barangayId) {
                            $fail('The selected purok does not belong to the assigned barangay.');
                        }
                    }
                }
            ],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The full name is required.',
            'email.required' => 'The email address is required.',
            'email.unique' => 'This email is already registered.',
            'role.required' => 'Please select a user role.',
            'role.in' => 'Invalid role selected.',
            'assigned_barangay_id.exists' => 'The selected barangay does not exist.',
            'assigned_purok_id.exists' => 'The selected purok does not exist.',
        ];
    }
}