<?php

namespace App\Http\Requests\Bhw;

use App\Models\ChildNutritionAssessmentFlag;
use App\Models\Resident;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreNutritionAssessmentFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'bhw';
    }

    public function rules(): array
    {
        return [
            'resident_id' => ['required', 'integer', 'exists:residents,id'],
            'flag_reason' => ['required', 'string', 'max:1500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $resident = Resident::query()->with('household.purok')->find($this->integer('resident_id'));

            if (! $resident || (int) $resident->household?->purok?->barangay_id !== (int) $this->user()->assigned_barangay_id) {
                $validator->errors()->add('resident_id', 'Select a verified child from your assigned barangay.');
                return;
            }

            if (! $resident->birth_date) {
                $validator->errors()->add('resident_id', 'This resident is missing a birth date and cannot be flagged for nutrition assessment.');
                return;
            }

            $ageInMonths = $resident->birth_date->copy()->startOfDay()->diffInMonths(Carbon::today(), false);

            if ($ageInMonths < 0 || $ageInMonths > 71) {
                $validator->errors()->add('resident_id', 'Nutrition assessment flags are limited to verified children aged 0 to 71 months.');
            }

            $existingFlag = ChildNutritionAssessmentFlag::query()
                ->where('resident_id', $resident->id)
                ->where('flag_status', ChildNutritionAssessmentFlag::STATUS_OPEN)
                ->exists();

            if ($existingFlag) {
                $validator->errors()->add('resident_id', 'An open nutrition assessment flag already exists for this child.');
            }
        });
    }
}
