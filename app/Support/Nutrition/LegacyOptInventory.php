<?php

namespace App\Support\Nutrition;

use App\Models\NutritionCampaignPeriod;
use App\Models\OptMeasurement;
use Illuminate\Support\Facades\Schema;

class LegacyOptInventory
{
    public function report(): array
    {
        $legacy = OptMeasurement::query();
        if (Schema::hasColumn('opt_measurements', 'opt_cycle_entry_id')) {
            $legacy->whereNull('opt_cycle_entry_id');
        }
        $campaigns = NutritionCampaignPeriod::query()->where('campaign_type', NutritionCampaignPeriod::TYPE_OPT_PLUS)
            ->withCount('optMeasurements')->orderBy('barangay_id')->orderBy('id')->get()->map(function ($campaign): array {
                $matches = [];
                $explicitRound = preg_match('/^(January|July) (\d{4}) OPT\+$/i', $campaign->name, $matches) === 1;
                $round = $explicitRound ? strtolower($matches[1]) : null;
                $consistentDate = $explicitRound && $campaign->starts_on && $campaign->starts_on->year === (int) $matches[2]
                    && $campaign->starts_on->month === OptCycleRules::ROUND_MONTHS[$round];
                $inconsistent = $campaign->optMeasurements()->where('barangay_id', '!=', $campaign->barangay_id)->count();
                $duplicateChildren = $campaign->optMeasurements()->select('resident_id')->whereNotNull('resident_id')->groupBy('resident_id')->havingRaw('count(*) > 1')->get()->count();

                return ['campaign_id' => $campaign->id, 'barangay_id' => $campaign->barangay_id, 'name' => $campaign->name,
                    'measurement_count' => $campaign->opt_measurements_count, 'multiple_readings_children' => $duplicateChildren,
                    'barangay_inconsistencies' => $inconsistent,
                    'classification' => $consistentDate && ! $inconsistent ? 'explicit_round_candidate_requires_review' : 'ambiguous_keep_legacy',
                    'historical_roster_known' => false, 'historical_demographics_snapshotted' => false];
            })->all();

        return ['legacy_measurements' => (clone $legacy)->count(),
            'without_campaign' => (clone $legacy)->whereNull('campaign_period_id')->count(),
            'campaign_barangay_inconsistencies' => (clone $legacy)->whereHas('campaignPeriod', fn ($q) => $q->whereColumn('nutrition_campaign_periods.barangay_id', '!=', 'opt_measurements.barangay_id'))->count(),
            'non_opt_campaign_measurements' => (clone $legacy)->whereHas('campaignPeriod', fn ($q) => $q->where('campaign_type', '!=', NutritionCampaignPeriod::TYPE_OPT_PLUS))->count(),
            'campaigns' => $campaigns, 'conversion_performed' => false];
    }
}
