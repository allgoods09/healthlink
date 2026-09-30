<?php

namespace App\Http\Requests\Bns;

use App\Support\Nutrition\OptCycleRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreOptCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'bns' && (bool) $this->user()?->assigned_barangay_id;
    }

    public function rules(): array
    {
        return OptCycleRules::cycleValidationRules();
    }

    public function withValidator($validator): void
    {
        $validator->after(OptCycleRules::validateReference(...));
    }

    public function attributes(): array
    {
        return ['reference_date' => 'cycle date'];
    }
}
