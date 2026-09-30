<?php

namespace App\Support\Population;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Resident;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class PoocPopulation
{
    public const FOUNDATION_TABLES = ['migrations', 'barangays', 'barangay_officials', 'puroks', 'users', 'households', 'residents',
        'resident_socio_economic_profiles', 'child_nutrition_profiles', 'settings', 'cache', 'cache_locks', 'sessions'];

    public static function operationalCounts(): array
    {
        $counts = [];
        foreach (Schema::getTableListing(DB::connection()->getDatabaseName(), false) as $table) {
            if (! in_array($table, self::FOUNDATION_TABLES, true)) {
                $counts[$table] = DB::table($table)->count();
            }
        }

        return $counts;
    }

    public function seed(): array
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Synthetic population is development/testing only.');
        }
        $config = config('pooc_population');
        $started = microtime(true);
        DB::transaction(function () use ($config): void {
            $pilot = Barangay::where('name', $config['barangay'])->lockForUpdate()->firstOrFail();
            $puroks = $pilot->puroks()->active()->orderBy('purok_number')->get();
            if ($puroks->isEmpty()) {
                throw new RuntimeException('Pooc has no configured puroks. Configure/approve them first; none will be invented.');
            }
            if (DB::table('puroks')->where('barangay_id', '!=', $pilot->id)->exists()
                || DB::table('users')->whereIn('role', ['secretary', 'bns', 'bhw'])->where(function ($q) use ($pilot) {
                    $q->whereNull('assigned_barangay_id')->orWhere('assigned_barangay_id', '!=', $pilot->id);
                })->exists()) {
                throw new RuntimeException('Non-pilot scoped data exists. Use an isolated clean development database; nothing was deleted.');
            }
            foreach (['households', 'residents', 'resident_socio_economic_profiles', 'child_nutrition_profiles'] as $table) {
                if (DB::table($table)->exists()) {
                    throw new RuntimeException('Population records already exist. Rerun refused; use a separate clean development database.');
                }
            }
            if (array_sum(self::operationalCounts())) {
                throw new RuntimeException('Operational records already exist. This phase requires an isolated population-only database.');
            }
            $families = (new PoocFamilyGenerator($config))->generate($puroks->pluck('purok_number')->all());
            $byNumber = $puroks->keyBy('purok_number');
            $socioRows = [];
            $caregiverRows = [];
            $residentSequence = 0;
            $timestamp = $config['as_of'].' 00:00:00';
            foreach ($families as $householdSequence => $family) {
                $purok = $byNumber[$family['purok_number']];
                $household = Household::create([
                    'official_household_code' => sprintf('HH-%04d-%05d', $pilot->id, $householdSequence + 1),
                    'mobile_uuid' => (string) Uuid::uuid5(Uuid::NAMESPACE_URL, 'healthlink.pooc.v'.$config['version'].'.household.'.$householdSequence),
                    'purok_id' => $purok->id, 'household_no' => (string) $family['household_no'],
                    'household_address' => 'Synthetic residence '.$family['household_no'].', '.$purok->display_name.', Pooc Oriental, Tubigon, Bohol',
                    'is_active' => true,
                ]);
                $ids = [];
                foreach ($family['members'] as $member) {
                    $payload = array_intersect_key($member, array_flip(['first_name', 'middle_name', 'last_name', 'suffix', 'birth_date', 'sex', 'civil_status', 'relationship_to_head']));
                    $resident = Resident::create($payload + [
                        'household_id' => $household->id, 'official_resident_code' => sprintf('RS-%04d-%05d', $pilot->id, ++$residentSequence),
                        'mobile_uuid' => (string) Uuid::uuid5(Uuid::NAMESPACE_URL, 'healthlink.pooc.v'.$config['version'].'.resident.'.$residentSequence),
                        'birth_place' => 'Synthetic birthplace, Tubigon, Bohol', 'citizenship' => 'Filipino',
                        'resident_status' => Resident::STATUS_ACTIVE, 'is_active' => true,
                        'status_notes' => 'Entirely synthetic Pooc pilot population v'.$config['version'].'; not an actual resident.',
                    ]);
                    $ids[] = $resident->id;
                    $socioRows[] = $member['socio'] + ['resident_id' => $resident->id, 'created_at' => $timestamp, 'updated_at' => $timestamp];
                }
                $household->update(['head_resident_id' => $ids[0]]);
                foreach ($family['members'] as $i => $member) {
                    if (CarbonImmutable::parse($member['birth_date'])->addYearsNoOverflow(5)->lte($config['as_of'])) {
                        continue;
                    }
                    $caregiver = $member['caregiver_index'];
                    $parent = $caregiver === null ? null : $family['members'][$caregiver];
                    $caregiverRows[] = ['resident_id' => $ids[$i], 'caregiver_resident_id' => $caregiver === null ? null : $ids[$caregiver],
                        'caregiver_name' => $parent ? trim(implode(' ', array_filter([$parent['first_name'], $parent['middle_name'], $parent['last_name'], $parent['suffix']]))) : null,
                        'caregiver_relationship' => $member['caregiver_relationship'], 'caregiver_confirmed_at' => $parent ? $timestamp : null,
                        'ip_membership' => null, 'ip_confirmed_at' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp];
                }
            }
            foreach (array_chunk($socioRows, 250) as $chunk) {
                DB::table('resident_socio_economic_profiles')->insert($chunk);
            }
            foreach (array_chunk($caregiverRows, 250) as $chunk) {
                DB::table('child_nutrition_profiles')->insert($chunk);
            }
            $audit = (new PoocPopulationAudit)->report();
            if (array_sum($audit['invalid']) || $audit['residents'] !== $config['target']) {
                throw new RuntimeException('Population failed validation; the entire creation was rolled back: '.json_encode($audit['invalid']));
            }
        });

        return ['version' => $config['version'], 'runtime_seconds' => round(microtime(true) - $started, 3)] + (new PoocPopulationAudit)->report();
    }
}
