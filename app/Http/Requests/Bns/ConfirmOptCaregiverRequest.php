<?php

namespace App\Http\Requests\Bns;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmOptCaregiverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'bns' && (bool) $this->user()?->assigned_barangay_id;
    }

    public function rules(): array
    {
        return [
            'caregiver_resident_id' => ['nullable', 'integer'],
            'caregiver_name' => ['nullable', 'string', 'max:255'],
            'caregiver_relationship' => ['nullable', 'string', 'max:80'],
            'ip_membership' => ['required', 'in:yes,no,unknown'],
            'update_cycle_snapshot' => ['nullable', 'boolean'],
            'correction_reason' => ['required_if:update_cycle_snapshot,1', 'nullable', 'string', 'max:1500'],
        ];
    }
}
