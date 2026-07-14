<?php

namespace App\Support\Nutrition;

use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class GrowthStandardsRepository
{
    private const AGE_BASED_FILES = [
        'weight_for_age' => [
            'Male' => 'wfa_boys_0-to-5-years_zscores.xlsx',
            'Female' => 'wfa_girls_0-to-5-years_zscores.xlsx',
        ],
        'length_height_for_age_0_23' => [
            'Male' => 'lhfa_boys_0-to-2-years_zscores.xlsx',
            'Female' => 'lhfa_girls_0-to-2-years_zscores.xlsx',
        ],
        'length_height_for_age_24_59' => [
            'Male' => 'lhfa_boys_2-to-5-years_zscores.xlsx',
            'Female' => 'lhfa_girls_2-to-5-years_zscores.xlsx',
        ],
    ];

    private const LENGTH_HEIGHT_FILES = [
        'weight_for_length_0_23' => [
            'Male' => 'wfl_boys_0-to-2-years_zscores.xlsx',
            'Female' => 'wfl_girls_0-to-2-years_zscores.xlsx',
        ],
        'weight_for_height_24_59' => [
            'Male' => 'wfh_boys_2-to-5-years_zscores.xlsx',
            'Female' => 'wfh_girls_2-to-5-years_zscores.xlsx',
        ],
    ];

    private static array $ageTables = [];
    private static array $lengthHeightTables = [];

    public function weightForAgeRow(string $sex, int $ageInMonths): array
    {
        return $this->ageRow('weight_for_age', $sex, $ageInMonths);
    }

    public function lengthHeightForAgeRow(string $sex, int $ageInMonths): array
    {
        $dataset = $ageInMonths < 24
            ? 'length_height_for_age_0_23'
            : 'length_height_for_age_24_59';

        return $this->ageRow($dataset, $sex, $ageInMonths);
    }

    public function weightForLengthHeightRow(string $sex, int $ageInMonths, float $lengthHeightCm): array
    {
        $dataset = $ageInMonths < 24
            ? 'weight_for_length_0_23'
            : 'weight_for_height_24_59';

        return $this->lengthHeightRow($dataset, $sex, $lengthHeightCm);
    }

    private function ageRow(string $dataset, string $sex, int $ageInMonths): array
    {
        $table = $this->loadAgeTable($dataset, $sex);

        if (! array_key_exists($ageInMonths, $table)) {
            throw new InvalidArgumentException("No WHO age reference row for {$sex} at {$ageInMonths} month(s).");
        }

        return $table[$ageInMonths];
    }

    private function lengthHeightRow(string $dataset, string $sex, float $lengthHeightCm): array
    {
        $table = $this->loadLengthHeightTable($dataset, $sex);
        $key = number_format(round($lengthHeightCm, 1), 1, '.', '');

        if (! array_key_exists($key, $table)) {
            throw new InvalidArgumentException("No WHO length/height reference row for {$sex} at {$key} cm.");
        }

        return $table[$key];
    }

    private function loadAgeTable(string $dataset, string $sex): array
    {
        $cacheKey = "{$dataset}:{$sex}";

        if (! isset(self::$ageTables[$cacheKey])) {
            self::$ageTables[$cacheKey] = $this->parseAgeTable(
                resource_path('data/who-child-growth/' . $this->resolveAgeFile($dataset, $sex))
            );
        }

        return self::$ageTables[$cacheKey];
    }

    private function loadLengthHeightTable(string $dataset, string $sex): array
    {
        $cacheKey = "{$dataset}:{$sex}";

        if (! isset(self::$lengthHeightTables[$cacheKey])) {
            self::$lengthHeightTables[$cacheKey] = $this->parseLengthHeightTable(
                resource_path('data/who-child-growth/' . $this->resolveLengthHeightFile($dataset, $sex))
            );
        }

        return self::$lengthHeightTables[$cacheKey];
    }

    private function resolveAgeFile(string $dataset, string $sex): string
    {
        return self::AGE_BASED_FILES[$dataset][$sex]
            ?? throw new InvalidArgumentException("Unsupported WHO age dataset [{$dataset}] for sex [{$sex}].");
    }

    private function resolveLengthHeightFile(string $dataset, string $sex): string
    {
        return self::LENGTH_HEIGHT_FILES[$dataset][$sex]
            ?? throw new InvalidArgumentException("Unsupported WHO length/height dataset [{$dataset}] for sex [{$sex}].");
    }

    private function parseAgeTable(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $rows = [];

        for ($rowIndex = 2; ; $rowIndex++) {
            $month = $sheet->getCell('A' . $rowIndex)->getValue();

            if ($month === null || $month === '') {
                break;
            }

            $row = $this->extractRow($sheet, $rowIndex, $highestColumn);
            $rows[(int) $month] = $row;
        }

        return $rows;
    }

    private function parseLengthHeightTable(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $rows = [];

        for ($rowIndex = 2; ; $rowIndex++) {
            $value = $sheet->getCell('A' . $rowIndex)->getValue();

            if ($value === null || $value === '') {
                break;
            }

            $row = $this->extractRow($sheet, $rowIndex, $highestColumn);
            $rows[number_format((float) $value, 1, '.', '')] = $row;
        }

        return $rows;
    }

    private function extractRow(mixed $sheet, int $rowIndex, int $highestColumn): array
    {
        $headers = [];

        for ($columnIndex = 1; $columnIndex <= $highestColumn; $columnIndex++) {
            $header = (string) $sheet->getCell(Coordinate::stringFromColumnIndex($columnIndex) . '1')->getFormattedValue();

            if ($header === '') {
                continue;
            }

            $headers[$columnIndex] = $header;
        }

        $row = [];

        foreach ($headers as $columnIndex => $header) {
            $value = $sheet->getCell(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex)->getValue();
            $normalizedHeader = strtolower(str_replace([' ', '-'], '_', trim($header)));
            $row[$normalizedHeader] = is_numeric($value) ? (float) $value : $value;
        }

        return $row;
    }
}
