<?php

namespace App\Support\Nutrition;

use App\Models\Resident;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OptCycleRules
{
    public const VERSION = 'pooc-pilot-v1';

    public const ROUNDS = ['january' => 'January', 'july' => 'July'];

    public const ROUND_MONTHS = ['january' => 1, 'july' => 7];

    public static function cycleValidationRules(): array
    {
        return [
            'year' => ['required', 'integer', 'between:1900,2100'],
            'round' => ['required', Rule::in(array_keys(self::ROUNDS))],
            'reference_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    public static function validateReference(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }
        $data = $validator->getData();
        $reference = CarbonImmutable::parse($data['reference_date']);
        if ($reference->year !== (int) $data['year'] || $reference->month !== self::ROUND_MONTHS[$data['round']]) {
            $validator->errors()->add('reference_date', 'Choose a cycle date in the selected year and round.');
        }
    }

    public static function measurementValidationRules(): array
    {
        return [
            'measurement_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'weight_kg' => ['required', 'numeric', 'between:0.5,60'],
            'height_cm' => ['required', 'numeric', 'between:30,140'],
            'measurement_posture' => ['required', 'in:standing,recumbent'],
            'remarks' => ['nullable', 'string', 'max:1500'],
        ];
    }

    public static function eligibleDob(?string $dob, CarbonInterface $reference): bool
    {
        try {
            $birth = CarbonImmutable::createFromFormat('!Y-m-d', (string) $dob);
            if (! $birth || $birth->format('Y-m-d') !== $dob) {
                return false;
            }

            // Pilot convention: a leap-day child's non-leap fifth birthday is February 28.
            return $birth->lte($reference) && $reference->lt($birth->addYearsNoOverflow(5));
        } catch (\Throwable) {
            return false;
        }
    }

    public static function ageMonths(CarbonInterface $birth, CarbonInterface $date): int
    {
        return max(0, (int) floor($birth->copy()->startOfDay()->diffInMonths($date->copy()->startOfDay(), false)));
    }

    public static function residents(int $barangayId, CarbonInterface $reference): Builder
    {
        return Resident::currentPopulation()
            ->whereHas('household.purok', fn ($q) => $q->where('barangay_id', $barangayId))
            ->whereDate('birth_date', '<=', $reference->format('Y-m-d'))
            ->whereDate('birth_date', '>=', $reference->copy()->subYears(6)->format('Y-m-d'));
    }
}
