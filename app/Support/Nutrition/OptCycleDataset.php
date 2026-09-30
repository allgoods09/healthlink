<?php

namespace App\Support\Nutrition;

use App\Models\OptCycle;
use App\Models\OptCycleEntry;
use App\Models\OptMeasurement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

// Canonical snapshot inputs, independent of any official workbook's cells or formulas.
class OptCycleDataset
{
    public const VERSION = 'opt-inputs-v1';

    public const SORTS = ['name' => 'Child name', 'age' => 'Age', 'purok' => 'Purok', 'measurement_date' => 'Measurement date'];

    public function query(OptCycle $cycle, array $filters): Builder
    {
        $query = OptCycleEntry::query()->where('opt_cycle_id', $cycle->id)->with(['cycle', 'measurement']);
        if ($search = trim($filters['search'] ?? '')) {
            foreach (preg_split('/\s+/', $search) as $word) {
                $query->where(function ($q) use ($word): void {
                    foreach (['first_name', 'last_name', 'middle_name', 'resident_code'] as $field) {
                        $q->orWhere($field, 'like', '%'.trim($word, ',').'%');
                    }
                });
            }
        }
        if (! empty($filters['purok_key'])) {
            $query->where('purok_key', $filters['purok_key']);
        }
        if (($filters['measurement_status'] ?? '') === 'measured') {
            $query->whereHas('measurement');
        } elseif (($filters['measurement_status'] ?? '') === 'unmeasured') {
            $query->whereDoesntHave('measurement');
        }
        if (($filters['readiness'] ?? '') === 'ready') {
            $query->dataReady();
        } elseif (($filters['readiness'] ?? '') === 'incomplete') {
            $query->whereNotIn('id', OptCycleEntry::query()->where('opt_cycle_id', $cycle->id)->dataReady()->select('id'));
        }
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        match ($filters['sort'] ?? 'name') {
            'age' => $query->orderBy('birth_date', $direction === 'asc' ? 'desc' : 'asc'),
            'purok' => $query->orderBy('purok_name', $direction),
            'measurement_date' => $query->orderBy(OptMeasurement::select('measurement_date')->whereColumn('opt_cycle_entry_id', 'opt_cycle_entries.id'), $direction),
            default => $query->orderBy('last_name', $direction)->orderBy('first_name', $direction)->orderBy('middle_name', $direction),
        };

        return $query->orderBy('id', $direction);
    }

    public function rows(OptCycle $cycle, array $filters): Collection
    {
        return $this->query($cycle, $filters)->get()->map(fn (OptCycleEntry $entry) => [
            'cycle' => $cycle->title, 'reference_date' => $cycle->reference_date->format('Y-m-d'),
            'resident_code' => $entry->resident_code, 'child' => $entry->child_name,
            'first_name' => $entry->first_name, 'middle_name' => $entry->middle_name,
            'last_name' => $entry->last_name, 'suffix' => $entry->suffix,
            'dob' => $entry->birth_date->format('Y-m-d'), 'sex' => $entry->sex,
            'reference_age_months' => $entry->reference_age, 'barangay' => $entry->barangay_name,
            'municipality' => $entry->municipality, 'province' => $entry->province, 'region' => $entry->region,
            'psgc_code' => $entry->psgc_code, 'purok' => $entry->purok_name, 'address' => $entry->address,
            'household_code' => $entry->household_code, 'household_number' => $entry->household_number,
            'caregiver' => $entry->caregiver_name, 'caregiver_relationship' => $entry->caregiver_relationship,
            'caregiver_confirmed' => $entry->caregiver_confirmed_at ? 'Yes' : 'No',
            'ip_membership' => $entry->ip_membership === null ? 'Unknown' : ($entry->ip_membership ? 'Yes' : 'No'),
            'ip_confirmed' => $entry->ip_confirmed_at ? 'Yes' : 'No', 'ethnicity' => $entry->ethnicity,
            'measurement_date' => $entry->measurement?->measurement_date?->format('Y-m-d'),
            'weight_kg' => $entry->measurement ? (float) $entry->measurement->weight_kg : null,
            'height_cm' => $entry->measurement ? (float) $entry->measurement->height_cm : null,
            'posture' => $entry->measurement?->measurement_posture_label,
            'posture_code' => $entry->measurement?->measurement_posture,
            'status' => $entry->measurement_status, 'readiness' => implode('; ', $entry->readiness_issues) ?: 'Current inputs complete',
        ]);
    }

    public function columns(): array
    {
        return [
            'Cycle' => 'cycle', 'Reference Date' => 'reference_date', 'Resident Code' => 'resident_code', 'Child' => 'child',
            'First Name' => 'first_name', 'Middle Name' => 'middle_name', 'Last Name' => 'last_name', 'Suffix' => 'suffix',
            'DOB' => 'dob', 'Sex' => 'sex', 'Age at Reference (Months)' => 'reference_age_months',
            'Barangay' => 'barangay', 'Municipality' => 'municipality', 'Province' => 'province', 'Region' => 'region',
            'PSGC Code' => 'psgc_code', 'Purok' => 'purok', 'Address' => 'address', 'Household Code' => 'household_code',
            'Household Number' => 'household_number', 'Mother/Caregiver' => 'caregiver', 'Caregiver Relationship' => 'caregiver_relationship',
            'Caregiver Confirmed' => 'caregiver_confirmed', 'IP Membership' => 'ip_membership', 'IP Confirmed' => 'ip_confirmed', 'Ethnicity' => 'ethnicity',
            'Measurement Date' => 'measurement_date', 'Weight (kg)' => 'weight_kg', 'Height/Length (cm)' => 'height_cm',
            'Posture' => 'posture', 'Measurement Status' => 'status', 'Input Readiness' => 'readiness',
        ];
    }
}
