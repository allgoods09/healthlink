<?php

namespace App\Http\Requests\Admin\Geometry;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class BarangayStoreRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('barangays')->where(function ($query) {
                    return $query->where('municipality', $this->input('municipality', 'Tubigon'));
                })
            ],
            'psgc_code' => ['required', 'string', 'max:20', 'unique:barangays'],
            'municipality' => ['string', 'max:100'],
            'province' => ['string', 'max:100'],
            'region' => ['string', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The barangay name is required.',
            'name.unique' => 'A barangay with this name already exists in the municipality.',
            'psgc_code.required' => 'The PSGC code is required.',
            'psgc_code.unique' => 'This PSGC code is already registered.',
        ];
    }
}