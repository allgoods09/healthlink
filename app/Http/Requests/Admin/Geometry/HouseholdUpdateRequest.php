<?php

namespace App\Http\Requests\Admin\Geometry;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class HouseholdUpdateRequest extends FormRequest
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
        $householdId = $this->route('household') ? $this->route('household')->id : null;

        return [
            'purok_id' => [
                'required',
                'exists:puroks,id',
                function ($attribute, $value, $fail) {
                    $user = Auth::user();
                    
                    if ($user->role === 'bhw' && $user->assigned_purok_id != $value) {
                        $fail('You can only update households in your assigned purok.');
                    }
                    
                    if (in_array($user->role, ['secretary', 'bns'])) {
                        $purok = \App\Models\Purok::find($value);
                        if ($purok && $purok->barangay_id != $user->assigned_barangay_id) {
                            $fail('You can only update households in your assigned barangay.');
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
                })->ignore($householdId)
            ],
            'household_address' => ['required', 'string'],
            'is_social_aid_beneficiary' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}