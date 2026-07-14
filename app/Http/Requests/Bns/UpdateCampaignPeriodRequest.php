<?php

namespace App\Http\Requests\Bns;

use App\Models\NutritionCampaignPeriod;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCampaignPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'bns';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'campaign_type' => ['required', 'string', 'in:' . implode(',', array_keys(NutritionCampaignPeriod::TYPES))],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->boolean('is_active')) {
                return;
            }

            $campaignPeriod = $this->route('campaignPeriod');

            $exists = NutritionCampaignPeriod::query()
                ->where('barangay_id', $this->user()->assigned_barangay_id)
                ->where('campaign_type', $this->string('campaign_type')->toString())
                ->where('is_active', true)
                ->whereKeyNot($campaignPeriod?->id)
                ->exists();

            if ($exists) {
                $validator->errors()->add('is_active', 'Only one active campaign period per campaign type is allowed in this barangay.');
            }
        });
    }
}
