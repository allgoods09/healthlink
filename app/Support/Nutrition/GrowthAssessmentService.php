<?php

namespace App\Support\Nutrition;

use App\Models\Resident;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class GrowthAssessmentService
{
    public function __construct(
        private readonly GrowthStandardsRepository $standards,
    ) {
    }

    public function assess(
        Resident $resident,
        CarbonInterface $measurementDate,
        float $weightKg,
        float $lengthHeightCm,
        string $measurementPosture,
    ): array {
        if (! $resident->birth_date) {
            throw new InvalidArgumentException('Resident birth date is required for OPT+ assessment.');
        }

        $sex = $this->normalizeSex($resident->sex);
        $ageInMonths = $resident->birth_date->startOfDay()->diffInMonths($measurementDate->copy()->startOfDay());
        [$convertedLengthHeightCm, $basis] = $this->normalizeLengthHeightByAge(
            $ageInMonths,
            $lengthHeightCm,
            $measurementPosture
        );

        $weightForAgeRow = $this->standards->weightForAgeRow($sex, $ageInMonths);
        $heightForAgeRow = $this->standards->lengthHeightForAgeRow($sex, $ageInMonths);
        $weightForLengthHeightRow = $this->standards->weightForLengthHeightRow($sex, $ageInMonths, $convertedLengthHeightCm);

        $weightForAgeZScore = $this->calculateZScore($weightKg, $weightForAgeRow);
        $heightForAgeZScore = $this->calculateZScore($convertedLengthHeightCm, $heightForAgeRow);
        $weightForLengthHeightZScore = $this->calculateZScore($weightKg, $weightForLengthHeightRow);

        return [
            'age_in_months' => $ageInMonths,
            'sex_snapshot' => $sex,
            'normalized_length_height_cm' => $convertedLengthHeightCm,
            'normalized_length_height_basis' => $basis,
            'weight_for_age_z_score' => $weightForAgeZScore,
            'weight_for_age_status' => $this->weightForAgeStatus($weightForAgeZScore),
            'height_for_age_z_score' => $heightForAgeZScore,
            'height_for_age_status' => $this->heightForAgeStatus($heightForAgeZScore),
            'weight_for_length_height_z_score' => $weightForLengthHeightZScore,
            'weight_for_length_height_status' => $this->weightForLengthHeightStatus($weightForLengthHeightZScore),
        ];
    }

    private function normalizeSex(?string $sex): string
    {
        return match ($sex) {
            'Male', 'Female' => $sex,
            default => throw new InvalidArgumentException('Resident sex must be Male or Female for WHO growth assessment.'),
        };
    }

    private function normalizeLengthHeightByAge(int $ageInMonths, float $lengthHeightCm, string $measurementPosture): array
    {
        if ($ageInMonths < 24) {
            $converted = $measurementPosture === 'standing'
                ? $lengthHeightCm + 0.7
                : $lengthHeightCm;

            return [round($converted, 1), 'length'];
        }

        $converted = $measurementPosture === 'recumbent'
            ? $lengthHeightCm - 0.7
            : $lengthHeightCm;

        return [round($converted, 1), 'height'];
    }

    private function calculateZScore(float $measurement, array $referenceRow): float
    {
        $l = (float) $referenceRow['l'];
        $m = (float) $referenceRow['m'];
        $s = (float) $referenceRow['s'];

        if ($measurement <= 0 || $m <= 0 || $s <= 0) {
            throw new InvalidArgumentException('WHO growth assessment inputs must be positive.');
        }

        if ($l == 0.0) {
            return round(log($measurement / $m) / $s, 3);
        }

        return round((((($measurement / $m) ** $l) - 1) / ($l * $s)), 3);
    }

    private function weightForAgeStatus(float $zScore): string
    {
        return match (true) {
            $zScore < -3 => 'Severely Underweight',
            $zScore < -2 => 'Underweight',
            $zScore > 2 => 'Overweight',
            default => 'Normal',
        };
    }

    private function heightForAgeStatus(float $zScore): string
    {
        return match (true) {
            $zScore < -3 => 'Severely Stunted',
            $zScore < -2 => 'Stunted',
            $zScore > 3 => 'Tall',
            default => 'Normal',
        };
    }

    private function weightForLengthHeightStatus(float $zScore): string
    {
        return match (true) {
            $zScore < -3 => 'Severely Wasted',
            $zScore < -2 => 'Wasted',
            $zScore > 3 => 'Obese',
            $zScore > 2 => 'Overweight',
            default => 'Normal',
        };
    }
}
