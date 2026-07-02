<?php

namespace App\Http\Requests\Admin\Geometry;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class BarangayUpdateRequest extends FormRequest
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
        $barangayId = $this->route('barangay') ? $this->route('barangay')->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('barangays')->where(function ($query) {
                    return $query->where('municipality', $this->input('municipality', 'Tubigon'));
                })->ignore($barangayId)
            ],
            'psgc_code' => ['required', 'string', 'max:20', Rule::unique('barangays')->ignore($barangayId)],
            'municipality' => ['string', 'max:100'],
            'province' => ['string', 'max:100'],
            'region' => ['string', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }
}