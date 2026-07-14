<?php

namespace App\Http\Requests\Admin\Geometry;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class PurokUpdateRequest extends FormRequest
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
        $purokId = $this->route('purok') ? $this->route('purok')->id : null;

        return [
            'barangay_id' => ['required', 'exists:barangays,id'],
            'purok_number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('puroks')->where(function ($query) {
                    return $query->where('barangay_id', $this->input('barangay_id'));
                })->ignore($purokId)
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
}
