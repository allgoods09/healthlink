<?php

namespace App\Http\Requests\Bns;

use App\Models\FeedingProgram;
use App\Models\NutritionCampaignPeriod;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFeedingProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'bns';
    }

    public function rules(): array
    {
        return [
            'campaign_period_id' => ['nullable', 'integer', 'exists:nutrition_campaign_periods,id'],
            'name' => ['required', 'string', 'max:120'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'program_status' => ['required', 'string', 'in:' . implode(',', array_keys(FeedingProgram::STATUSES))],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->filled('campaign_period_id')) {
                return;
            }

            $campaignPeriod = NutritionCampaignPeriod::query()->find($this->integer('campaign_period_id'));

            if (! $campaignPeriod || (int) $campaignPeriod->barangay_id !== (int) $this->user()->assigned_barangay_id) {
                $validator->errors()->add('campaign_period_id', 'Select a valid campaign period from your assigned barangay.');
            }
        });
    }
}
