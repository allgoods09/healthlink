<?php

namespace App\Support\Population;

use App\Models\Barangay;
use App\Support\Nutrition\OptCycleRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PoocPopulationAudit
{
    public function report(): array
    {
        $date = CarbonImmutable::parse(config('pooc_population.as_of'));
        $pilot = Barangay::where('name', config('pooc_population.barangay'))->firstOrFail();
        $puroks = DB::table('puroks')->where('barangay_id', $pilot->id)->orderBy('purok_number')->get()->keyBy('id');
        $households = DB::table('households')->get()->keyBy('id');
        $residents = DB::table('residents')->get()->keyBy('id');
        $socio = DB::table('resident_socio_economic_profiles')->get()->keyBy('resident_id');
        $profiles = DB::table('child_nutrition_profiles')->get()->keyBy('resident_id');
        $invalid = array_fill_keys(['duplicate_names', 'residents_without_households', 'invalid_dob', 'impossible_minor_attributes',
            'invalid_household_relationships', 'invalid_scope_relationships', 'duplicate_household_numbers', 'invalid_caregiver_references',
            'non_pilot_residents', 'non_pilot_households', 'non_pilot_puroks', 'non_pilot_scoped_users', 'operational_records'], 0);
        $sex = ['Male' => 0, 'Female' => 0];
        $ages = ['0-4' => 0, '5-17' => 0, '18-59' => 0, '60+' => 0];
        $names = $surnames = $sizes = $ageRows = $numbers = [];
        $eligible = $infants = $linked = $missing = $populated = 0;
        $perPurok = [];
        foreach ($puroks as $purok) {
            $perPurok[$purok->id] = ['number' => $purok->purok_number, 'name' => $purok->purok_name, 'residents' => 0, 'households' => 0];
        }
        foreach ($households as $household) {
            if (! isset($puroks[$household->purok_id])) {
                $invalid['non_pilot_households']++;
                if (! DB::table('puroks')->where('id', $household->purok_id)->exists()) {
                    $invalid['invalid_scope_relationships']++;
                }

                continue;
            }
            $perPurok[$household->purok_id]['households']++;
            $sizes[$household->id] = 0;
            $key = $household->purok_id.'|'.$household->household_no;
            if (isset($numbers[$key])) {
                $invalid['duplicate_household_numbers']++;
            }
            $numbers[$key] = true;
        }
        foreach ($residents as $resident) {
            $household = $households[$resident->household_id] ?? null;
            if (! $household) {
                $invalid['residents_without_households']++;

                continue;
            }
            if (! isset($puroks[$household->purok_id])) {
                $invalid['non_pilot_residents']++;

                continue;
            }
            $populated++;
            $sizes[$household->id]++;
            $perPurok[$household->purok_id]['residents']++;
            $normalized = PoocNames::normalized((array) $resident);
            if (isset($names[$normalized])) {
                $invalid['duplicate_names']++;
            }
            $names[$normalized] = true;
            $surnames[$resident->last_name] = ($surnames[$resident->last_name] ?? 0) + 1;
            $sex[$resident->sex] = ($sex[$resident->sex] ?? 0) + 1;
            try {
                $birth = CarbonImmutable::createFromFormat('!Y-m-d', (string) $resident->birth_date);
                if (! $birth || $birth->toDateString() !== $resident->birth_date || $birth->gt($date)) {
                    throw new \RuntimeException;
                }
                $age = (int) floor($birth->diffInYears($date));
                if ($age > 110) {
                    throw new \RuntimeException;
                }
            } catch (\Throwable) {
                $invalid['invalid_dob']++;

                continue;
            }
            $ageRows[] = ['age' => $age, 'birth_date' => $resident->birth_date];
            $ages[$age < 5 ? '0-4' : ($age < 18 ? '5-17' : ($age < 60 ? '18-59' : '60+'))]++;
            if ($age === 0) {
                $infants++;
            }
            $occupation = $socio[$resident->id]->occupation ?? null;
            if ($age < 18 && ($resident->civil_status !== 'Single' || ! in_array($occupation, [null, 'Student'], true))) {
                $invalid['impossible_minor_attributes']++;
            }
            if ($age < 6 && $occupation !== null) {
                $invalid['impossible_minor_attributes']++;
            }
            $head = $residents[$household->head_resident_id] ?? null;
            $badRelationship = ! $head || $head->household_id !== $resident->household_id;
            if ($head) {
                $headAge = (int) floor(CarbonImmutable::parse($head->birth_date)->diffInYears($date));
                $badRelationship = $badRelationship || $headAge < 18 || $head->relationship_to_head !== 'Head of Household';
                $badRelationship = $badRelationship || match ($resident->relationship_to_head) {
                    'Head of Household' => $head->id !== $resident->id,
                    'Spouse' => $age < 18 || abs($headAge - $age) > 30,
                    'Child' => $headAge - $age < 18,
                    'Grandchild' => $headAge - $age < 35,
                    'Parent' => $age - $headAge < 18,
                    'Other Relative' => false,
                    default => true,
                };
            }
            if ($badRelationship) {
                $invalid['invalid_household_relationships']++;
            }
            if (OptCycleRules::eligibleDob($resident->birth_date, $date)) {
                $eligible++;
                $profile = $profiles[$resident->id] ?? null;
                if ($profile?->caregiver_resident_id) {
                    $linked++;
                } else {
                    $missing++;
                }
            }
        }
        foreach ($profiles as $profile) {
            $child = $residents[$profile->resident_id] ?? null;
            if (! $child) {
                $invalid['invalid_caregiver_references']++;

                continue;
            }
            if (! $profile->caregiver_resident_id) {
                if ($profile->caregiver_name || $profile->caregiver_confirmed_at) {
                    $invalid['invalid_caregiver_references']++;
                }

                continue;
            }
            $caregiver = $residents[$profile->caregiver_resident_id] ?? null;
            $valid = $caregiver && $caregiver->household_id === $child->household_id && $caregiver->id !== $child->id;
            if ($valid) {
                $adultAge = (int) floor(CarbonImmutable::parse($caregiver->birth_date)->diffInYears($date));
                $childAge = (int) floor(CarbonImmutable::parse($child->birth_date)->diffInYears($date));
                $gap = in_array($profile->caregiver_relationship, ['Grandmother', 'Grandfather']) ? 35 : 18;
                $fullName = trim(implode(' ', array_filter([$caregiver->first_name, $caregiver->middle_name, $caregiver->last_name, $caregiver->suffix])));
                $valid = $adultAge >= 18 && $adultAge - $childAge >= $gap && $fullName === $profile->caregiver_name && $profile->caregiver_confirmed_at;
            }
            if (! $valid) {
                $invalid['invalid_caregiver_references']++;
            }
        }
        $invalid['non_pilot_puroks'] = DB::table('puroks')->where('barangay_id', '!=', $pilot->id)->count();
        foreach ($sizes as $size) {
            if ($size === 0) {
                $invalid['invalid_household_relationships']++;
            }
        }
        $invalid['invalid_scope_relationships'] += DB::table('users')->join('puroks', 'puroks.id', '=', 'users.assigned_purok_id')
            ->whereColumn('users.assigned_barangay_id', '!=', 'puroks.barangay_id')->count();
        $invalid['non_pilot_scoped_users'] = DB::table('users')->whereIn('role', ['secretary', 'bns', 'bhw'])
            ->where(fn ($q) => $q->whereNull('assigned_barangay_id')->orWhere('assigned_barangay_id', '!=', $pilot->id))->count();
        $operational = PoocPopulation::operationalCounts();
        $invalid['operational_records'] = array_sum($operational);
        arsort($surnames);
        usort($ageRows, fn ($a, $b) => strcmp($b['birth_date'], $a['birth_date']));
        $histogram = array_count_values($sizes);
        ksort($histogram);

        return ['as_of' => $date->toDateString(), 'barangays' => DB::table('barangays')->count(), 'residents' => $populated,
            'sex' => $sex, 'ages' => $ages, 'infants' => $infants, 'children_0_59_months' => $eligible,
            'households' => count($sizes), 'average_household_size' => count($sizes) ? round(array_sum($sizes) / count($sizes), 3) : 0,
            'smallest_household' => $sizes ? min($sizes) : 0, 'largest_household' => $sizes ? max($sizes) : 0,
            'household_size_distribution' => $histogram, 'youngest' => $ageRows[0] ?? null, 'oldest' => $ageRows ? end($ageRows) : null,
            'puroks_populated' => count(array_filter($perPurok, fn ($p) => $p['residents'] > 0)), 'per_purok' => array_values($perPurok),
            'caregivers' => ['linked_under_five' => $linked, 'unconfirmed_under_five' => $missing],
            'top_20_surnames' => array_slice($surnames, 0, 20, true), 'invalid' => $invalid, 'operational_counts' => $operational];
    }
}
