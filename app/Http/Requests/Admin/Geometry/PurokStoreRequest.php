<?php

namespace App\Http\Requests\Admin\Geometry;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class PurokStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return in_array(Auth::user()->role, ['admin', 'secretary']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'barangay_id' => ['required', 'exists:barangays,id'],
            'purok_number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('puroks')->where(function ($query) {
                    return $query->where('barangay_id', $this->input('barangay_id'));
                })
            ],
            'purok_name' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $user = Auth::user();

            if ($user->role === 'secretary' && (int) $this->input('barangay_id') !== (int) $user->assigned_barangay_id) {
                $validator->errors()->add('barangay_id', 'You can only manage puroks in your assigned barangay.');
            }
        });
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'barangay_id.required' => 'Please select a barangay.',
            'barangay_id.exists' => 'The selected barangay does not exist.',
            'purok_number.required' => 'The purok number is required.',
            'purok_number.unique' => 'This purok number already exists in the selected barangay.',
            'purok_number.min' => 'Purok number must be at least 1.',
        ];
    }
}
