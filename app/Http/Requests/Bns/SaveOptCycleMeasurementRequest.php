<?php

namespace App\Http\Requests\Bns;

use App\Support\Nutrition\OptCycleRules;
use Illuminate\Foundation\Http\FormRequest;

class SaveOptCycleMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'bns' && (bool) $this->user()?->assigned_barangay_id;
    }

    public function rules(): array
    {
        return OptCycleRules::measurementValidationRules() + [
            'caregiver_name' => ['nullable', 'string', 'max:255'],
            'caregiver_resident_id' => ['nullable', 'integer'],
        ];
    }
}
