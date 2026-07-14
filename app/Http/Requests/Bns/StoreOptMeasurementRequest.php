<?php

namespace App\Http\Requests\Bns;

use App\Models\NutritionCampaignPeriod;
use App\Models\OptMeasurement;
use App\Models\Resident;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreOptMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'bns';
    }

    public function rules(): array
    {
        return [
            'resident_id' => ['required', 'integer', 'exists:residents,id'],
            'campaign_period_id' => ['required', 'integer', 'exists:nutrition_campaign_periods,id'],
            'measurement_date' => ['required', 'date', 'before_or_equal:today'],
            'weight_kg' => ['required', 'numeric', 'min:0.5', 'max:60'],
            'height_cm' => ['required', 'numeric', 'min:30', 'max:140'],
            'measurement_posture' => ['required', 'string', 'in:' . OptMeasurement::POSTURE_STANDING . ',' . OptMeasurement::POSTURE_RECUMBENT],
            'remarks' => ['nullable', 'string', 'max:1500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $resident = Resident::query()
                ->with('household.purok')
                ->find($this->integer('resident_id'));

            $campaignPeriod = NutritionCampaignPeriod::query()->find($this->integer('campaign_period_id'));

            if (! $resident || (int) $resident->household?->purok?->barangay_id !== (int) $this->user()->assigned_barangay_id) {
                $validator->errors()->add('resident_id', 'Select a verified resident from your assigned barangay.');
            }

            if (! $campaignPeriod || (int) $campaignPeriod->barangay_id !== (int) $this->user()->assigned_barangay_id) {
                $validator->errors()->add('campaign_period_id', 'Select a valid campaign period from your assigned barangay.');
            }

            if ($campaignPeriod && $campaignPeriod->campaign_type !== NutritionCampaignPeriod::TYPE_OPT_PLUS) {
                $validator->errors()->add('campaign_period_id', 'OPT+ measurements can only be logged under an OPT+ campaign period.');
            }

            if ($resident && ! $resident->birth_date) {
                $validator->errors()->add('resident_id', 'This resident is missing a birth date and cannot be assessed in OPT+ yet.');
            }

            if ($resident && ($resident->resident_status !== Resident::STATUS_ACTIVE || ! $resident->is_active)) {
                $validator->errors()->add('resident_id', 'Only active verified residents can receive new OPT+ measurements.');
            }

            if ($resident && ! in_array($resident->sex, ['Male', 'Female'], true)) {
                $validator->errors()->add('resident_id', 'This resident must have a Male or Female sex value for WHO growth assessment.');
            }

            if (! $resident || ! $resident->birth_date || ! $this->filled('measurement_date')) {
                return;
            }

            $measurementDate = Carbon::parse($this->input('measurement_date'))->startOfDay();
            $ageInMonths = $resident->birth_date->copy()->startOfDay()->diffInMonths($measurementDate, false);

            if ($ageInMonths < 0) {
                $validator->errors()->add('measurement_date', 'Measurement date cannot be earlier than the resident birth date.');
            }

            if ($ageInMonths > 59) {
                $validator->errors()->add('resident_id', 'OPT+ measurements currently support verified children aged 0 to 59 months only.');
            }

            if ($campaignPeriod && $campaignPeriod->starts_on && $measurementDate->lt($campaignPeriod->starts_on->copy()->startOfDay())) {
                $validator->errors()->add('measurement_date', 'Measurement date cannot be earlier than the campaign start date.');
            }

            if ($campaignPeriod && $campaignPeriod->ends_on && $measurementDate->gt($campaignPeriod->ends_on->copy()->endOfDay())) {
                $validator->errors()->add('measurement_date', 'Measurement date cannot be later than the campaign end date.');
            }

            $isDuplicate = OptMeasurement::query()
                ->where('resident_id', $resident->id)
                ->where('campaign_period_id', $this->integer('campaign_period_id'))
                ->whereDate('measurement_date', $measurementDate)
                ->exists();

            if ($isDuplicate) {
                $validator->errors()->add('measurement_date', 'A measurement for this child already exists on this date within the selected campaign period.');
            }
        });
    }
}
