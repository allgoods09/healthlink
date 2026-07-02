<?php

namespace App\Http\Requests\Admin\IAM;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UserStoreRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in(['admin', 'mho', 'phn', 'secretary', 'bns', 'bhw'])],
            'assigned_barangay_id' => [
                'nullable',
                'exists:barangays,id',
                function ($attribute, $value, $fail) {
                    $role = $this->input('role');
                    
                    // Global roles (admin, mho, phn) must have NULL barangay
                    if (in_array($role, ['admin', 'mho', 'phn']) && !is_null($value)) {
                        $fail('Global roles (Admin, MHO, PHN) cannot be assigned to a specific barangay.');
                    }
                    
                    // Local roles (secretary, bns, bhw) must have a barangay
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
                    
                    // Only BHWs can have a purok assignment
                    if ($role !== 'bhw' && !is_null($value)) {
                        $fail('Only BHWs can be assigned to a specific purok.');
                    }
                    
                    // BHWs must have a purok assignment
                    if ($role === 'bhw' && is_null($value)) {
                        $fail('BHWs must be assigned to a specific purok.');
                    }
                    
                    // If purok is provided, it must belong to the assigned barangay
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
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The full name is required.',
            'email.required' => 'The email address is required.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'A password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'role.required' => 'Please select a user role.',
            'role.in' => 'Invalid role selected.',
            'assigned_barangay_id.exists' => 'The selected barangay does not exist.',
            'assigned_purok_id.exists' => 'The selected purok does not exist.',
        ];
    }
}