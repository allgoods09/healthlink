<?php

namespace App\Http\Requests\Bns;

use App\Models\MaternalNutritionProfile;
use App\Models\MicronutrientSupplementationLog;
use App\Models\Resident;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreMicronutrientLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'bns';
    }

    public function rules(): array
    {
        return [
            'resident_id' => ['required', 'integer', 'exists:residents,id'],
            'administered_on' => ['required', 'date', 'before_or_equal:today'],
            'supplement_type' => ['required', 'string', 'in:' . implode(',', array_keys(MicronutrientSupplementationLog::SUPPLEMENT_TYPES))],
            'recipient_category' => ['required', 'string', 'in:' . implode(',', array_keys(MicronutrientSupplementationLog::RECIPIENT_CATEGORIES))],
            'dose_description' => ['nullable', 'string', 'max:120'],
            'remarks' => ['nullable', 'string', 'max:1500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $resident = Resident::query()->with('household.purok', 'maternalNutritionProfile')->find($this->integer('resident_id'));

            if (! $resident || ! $this->filled('administered_on')) {
                return;
            }

            if ((int) $resident->household?->purok?->barangay_id !== (int) $this->user()->assigned_barangay_id) {
                $validator->errors()->add('resident_id', 'Select a verified resident from your assigned barangay.');
                return;
            }

            $administeredOn = Carbon::parse($this->input('administered_on'))->startOfDay();
            $category = $this->string('recipient_category')->toString();

            if ($category === MicronutrientSupplementationLog::RECIPIENT_TODDLER) {
                if (! $resident->birth_date) {
                    $validator->errors()->add('resident_id', 'This child is missing a birth date and cannot be validated for supplementation.');
                    return;
                }

                $ageInMonths = $resident->birth_date->copy()->startOfDay()->diffInMonths($administeredOn, false);

                if ($ageInMonths < 0 || $ageInMonths > 71) {
                    $validator->errors()->add('resident_id', 'Toddler supplementation is currently limited to verified children aged 0 to 71 months on the administration date.');
                }
            }

            if ($category === MicronutrientSupplementationLog::RECIPIENT_PREGNANT_WOMAN) {
                if (! $resident->maternalNutritionProfile?->is_currently_pregnant) {
                    $validator->errors()->add('resident_id', 'This resident is not currently marked pregnant in maternal tracking.');
                }
            }

            if ($category === MicronutrientSupplementationLog::RECIPIENT_LACTATING_MOTHER) {
                if (! $resident->maternalNutritionProfile?->is_currently_lactating) {
                    $validator->errors()->add('resident_id', 'This resident is not currently marked lactating in maternal tracking.');
                }
            }
        });
    }
}
