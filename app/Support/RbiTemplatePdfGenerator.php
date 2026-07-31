<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Resident;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

class RbiTemplatePdfGenerator
{
    private const RESIDENT_TEMPLATE = 'resources/document-templates/rbi-form-b-resident.pdf';
    private const HOUSEHOLD_TEMPLATE = 'resources/document-templates/rbi-form-a-household.pdf';

    private const RESIDENT_SCALE = 0.48;
    private const HOUSEHOLD_SCALE = 0.48;

    /**
     * @param  iterable<Resident>  $residents
     */
    public function generateResidents(iterable $residents, array $context = []): string
    {
        $pdf = new Fpdi('P', 'pt');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        $templatePath = base_path(self::RESIDENT_TEMPLATE);
        $pdf->setSourceFile($templatePath);
        $templateId = $pdf->importPage(1);
        $templateSize = $pdf->getTemplateSize($templateId);

        foreach (Collection::make($residents) as $resident) {
            $pdf->AddPage($templateSize['orientation'], [$templateSize['width'], $templateSize['height']]);
            $pdf->useTemplate($templateId);
            $this->drawResidentPage($pdf, $resident, $context);
        }

        return $pdf->Output('S');
    }

    /**
     * @param  iterable<Household>  $households
     */
    public function generateHouseholds(iterable $households, array $context = []): string
    {
        $pdf = new Fpdi('L', 'pt');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        $templatePath = base_path(self::HOUSEHOLD_TEMPLATE);
        $pdf->setSourceFile($templatePath);
        $templateId = $pdf->importPage(1);
        $templateSize = $pdf->getTemplateSize($templateId);

        foreach (Collection::make($households) as $household) {
            $rows = $household->residents
                ->sortBy(fn (Resident $resident) => Str::lower(trim($resident->last_name.'|'.$resident->first_name.'|'.$resident->middle_name)))
                ->values()
                ->chunk(12);

            if ($rows->isEmpty()) {
                $rows = collect([collect()]);
            }

            $lastChunkIndex = $rows->count() - 1;

            foreach ($rows as $chunkIndex => $chunk) {
                $pdf->AddPage($templateSize['orientation'], [$templateSize['width'], $templateSize['height']]);
                $pdf->useTemplate($templateId);
                $this->drawHouseholdPage($pdf, $household, $chunk, $chunkIndex === $lastChunkIndex, $context);
            }
        }

        return $pdf->Output('S');
    }

    private function drawResidentPage(Fpdi $pdf, Resident $resident, array $context): void
    {
        $barangay = $resident->household?->purok?->barangay;
        $profile = $resident->socioEconomicProfile;
        $secretaryName = $context['barangay_secretary_name'] ?? null;

        $this->writeLineText($pdf, 'REGION VII', 258, 202, 164, scale: self::RESIDENT_SCALE, size: 10, lift: 10.0);
        $this->writeLineText($pdf, strtoupper((string) ($barangay?->province ?? 'BOHOL')), 258, 236, 264, scale: self::RESIDENT_SCALE, size: 10, lift: 10.0);
        $this->writeLineText($pdf, strtoupper((string) ($barangay?->municipality ?? 'TUBIGON')), 858, 202, 252, scale: self::RESIDENT_SCALE, size: 10, lift: 10.0);
        $this->writeLineText($pdf, strtoupper((string) ($barangay?->name ?? '')), 858, 236, 252, scale: self::RESIDENT_SCALE, size: 10, lift: 10.0);

        $this->writeBoxText($pdf, $resident->philsys_card_no, 126, 339, 295, 39, scale: self::RESIDENT_SCALE, size: 10);
        $this->writeBoxText($pdf, $resident->last_name, 127, 458, 296, 39, scale: self::RESIDENT_SCALE, size: 10);
        $this->writeBoxText($pdf, $resident->suffix, 439, 458, 145, 39, scale: self::RESIDENT_SCALE, size: 10, align: 'C');
        $this->writeBoxText($pdf, $resident->first_name, 600, 458, 298, 39, scale: self::RESIDENT_SCALE, size: 10);
        $this->writeBoxText($pdf, $resident->middle_name, 915, 458, 251, 39, scale: self::RESIDENT_SCALE, size: 10);

        $this->writeBoxText($pdf, $resident->birth_date?->format('m/d/Y'), 129, 562, 186, 45, scale: self::RESIDENT_SCALE, size: 9, align: 'C');
        $this->writeBoxText($pdf, $resident->birth_place, 323, 562, 340, 45, scale: self::RESIDENT_SCALE, size: 9);
        $this->writeBoxText($pdf, $resident->sex, 671, 562, 68, 45, scale: self::RESIDENT_SCALE, size: 9, align: 'C');
        $this->writeBoxText($pdf, $resident->civil_status, 745, 562, 143, 45, scale: self::RESIDENT_SCALE, size: 9, align: 'C');
        $this->writeBoxText($pdf, $resident->religion, 902, 562, 263, 45, scale: self::RESIDENT_SCALE, size: 9);

        $this->writeBoxText(
            $pdf,
            $this->residentAddress($resident),
            130,
            668,
            727,
            43,
            scale: self::RESIDENT_SCALE,
            size: 9
        );
        $this->writeBoxText($pdf, $resident->citizenship, 868, 668, 298, 43, scale: self::RESIDENT_SCALE, size: 9);
        $this->writeBoxText($pdf, $profile?->occupation, 133, 766, 337, 44, scale: self::RESIDENT_SCALE, size: 9);
        $this->writeBoxText($pdf, $resident->contact_number, 488, 766, 369, 44, scale: self::RESIDENT_SCALE, size: 9, align: 'C');
        $this->writeBoxText($pdf, $resident->email_address, 874, 766, 292, 44, scale: self::RESIDENT_SCALE, size: 8.5, align: 'C');

        $this->markResidentEducation($pdf, $profile?->highest_education_level, $profile?->education_status);

        $this->writeLineText(
            $pdf,
            $this->formatAccomplishedDate($resident->created_at),
            149,
            1160,
            282,
            scale: self::RESIDENT_SCALE,
            size: 10,
            align: 'C'
        );
        $this->writeLineText(
            $pdf,
            $this->resolveResidentAccomplishingParty($resident),
            675,
            1153,
            426,
            scale: self::RESIDENT_SCALE,
            size: 9,
            align: 'C'
        );
        $this->writeLineText(
            $pdf,
            $secretaryName,
            130,
            1401,
            311,
            scale: self::RESIDENT_SCALE,
            size: 10,
            align: 'C'
        );
        $this->writeBoxText(
            $pdf,
            (string) ($resident->household?->household_no ?? ''),
            316,
            1478,
            238,
            54,
            scale: self::RESIDENT_SCALE,
            size: 10,
            align: 'C'
        );
    }

    /**
     * @param  Collection<int, Resident>  $residents
     */
    private function drawHouseholdPage(Fpdi $pdf, Household $household, Collection $residents, bool $isFinalPage, array $context): void
    {
        $barangay = $household->purok?->barangay;
        $officials = $context['officials'] ?? [];
        $preparedByName = $this->resolveHouseholdPreparedBy($household);
        $secretaryName = $officials['barangay_secretary_name'] ?? null;
        $punongBarangayName = $officials['punong_barangay_name'] ?? null;

        $this->writeLineText($pdf, 'REGION VII', 324, 238, 432, scale: self::HOUSEHOLD_SCALE, size: 10, lift: 3.2);
        $this->writeLineText($pdf, strtoupper((string) ($barangay?->province ?? 'BOHOL')), 324, 272, 432, scale: self::HOUSEHOLD_SCALE, size: 10, lift: 3.2);
        $this->writeLineText($pdf, strtoupper((string) ($barangay?->municipality ?? 'TUBIGON')), 324, 305, 432, scale: self::HOUSEHOLD_SCALE, size: 10, lift: 3.2);
        $this->writeLineText($pdf, strtoupper((string) ($barangay?->name ?? '')), 324, 339, 432, scale: self::HOUSEHOLD_SCALE, size: 10, lift: 3.2);
        $this->writeLineText($pdf, $household->household_address, 324, 372, 432, scale: self::HOUSEHOLD_SCALE, size: 8.4, lift: 4.1);
        $this->writeLineText($pdf, (string) max($household->residents->count(), 1), 338, 405, 418, scale: self::HOUSEHOLD_SCALE, size: 10, lift: 2.9);

        $rowBounds = [
            [582, 608],
            [608, 634.5],
            [634.5, 660.5],
            [660.5, 687],
            [687, 713.5],
            [713.5, 740],
            [740, 766],
            [766, 792.5],
            [792.5, 819],
            [819, 845.5],
            [845.5, 872],
            [872, 898],
        ];

        foreach ($residents->values() as $index => $resident) {
            $this->drawHouseholdResidentRow($pdf, $resident, $rowBounds[$index][0], $rowBounds[$index][1]);
        }

        if (! $isFinalPage) {
            return;
        }

        $this->writeLineText($pdf, $preparedByName, 72, 1001, 489, scale: self::HOUSEHOLD_SCALE, size: 10, align: 'C');
        $this->writeLineText($pdf, $secretaryName, 839, 1001, 346, scale: self::HOUSEHOLD_SCALE, size: 10, align: 'C');
        $this->writeLineText($pdf, $punongBarangayName, 1256, 1001, 485, scale: self::HOUSEHOLD_SCALE, size: 10, align: 'C');
    }

    private function drawHouseholdResidentRow(Fpdi $pdf, Resident $resident, float $rowTop, float $rowBottom): void
    {
        $profile = $resident->socioEconomicProfile;
        $rowHeight = max($rowBottom - $rowTop, 20);
        $boxTop = $rowTop + 2;
        $boxHeight = max($rowHeight - 4, 16);

        $this->writeBoxText($pdf, $resident->last_name, 75, $boxTop, 249, $boxHeight, scale: self::HOUSEHOLD_SCALE, size: 7.0);
        $this->writeBoxText($pdf, $resident->first_name, 324, $boxTop, 239, $boxHeight, scale: self::HOUSEHOLD_SCALE, size: 6.8, paddingX: 3.3);
        $this->writeBoxText($pdf, $resident->middle_name, 563, $boxTop, 190, $boxHeight, scale: self::HOUSEHOLD_SCALE, size: 6.8, paddingX: 8.0);
        $this->writeBoxText($pdf, $resident->suffix, 753, $boxTop, 86, $boxHeight, scale: self::HOUSEHOLD_SCALE, size: 6.6, align: 'C');
        $this->writeBoxText($pdf, $resident->birth_place, 839, $boxTop, 169, $boxHeight, scale: self::HOUSEHOLD_SCALE, size: 6.0, paddingX: 8.0);
        $this->writeBoxText($pdf, $resident->birth_date?->format('m/d/Y'), 1008, $boxTop, 206, $boxHeight, scale: self::HOUSEHOLD_SCALE, size: 6.6, align: 'C');
        $this->writeBoxText($pdf, $resident->age > 0 ? (string) $resident->age : '', 1214, $boxTop, 72, $boxHeight, scale: self::HOUSEHOLD_SCALE, size: 6.8, align: 'C');
        $this->writeBoxText($pdf, Str::upper(Str::substr((string) $resident->sex, 0, 1)), 1286, $boxTop, 86, $boxHeight, scale: self::HOUSEHOLD_SCALE, size: 6.8, align: 'C');
        $this->writeBoxText($pdf, $resident->civil_status, 1372, $boxTop, 112, $boxHeight, scale: self::HOUSEHOLD_SCALE, size: 6.2, align: 'C');
        $this->writeBoxText($pdf, $resident->citizenship, 1484, $boxTop, 131, $boxHeight, scale: self::HOUSEHOLD_SCALE, size: 6.2, align: 'C');
        $this->writeBoxText($pdf, $profile?->occupation, 1615, $boxTop, 171, $boxHeight, scale: self::HOUSEHOLD_SCALE, size: 6.1);
        $this->writeBoxText($pdf, $this->householdIndicatorSummary($resident), 1786, $boxTop, 246, $boxHeight, scale: self::HOUSEHOLD_SCALE, size: 5.4);
    }

    private function markResidentEducation(Fpdi $pdf, ?string $highestEducation, ?string $educationStatus): void
    {
        $normalizedLevel = Str::lower(trim((string) $highestEducation));
        $normalizedStatus = Str::lower(trim((string) $educationStatus));

        $boxMap = [
            'elementary' => [382, 865],
            'high school' => [534, 865],
            'college' => [698, 865],
            'post grad' => [830, 865],
            'post graduate' => [830, 865],
            'vocational' => [980, 865],
        ];

        $matched = false;

        foreach ($boxMap as $keyword => [$x, $y]) {
            if ($normalizedLevel !== '' && Str::contains($normalizedLevel, $keyword)) {
                $this->drawCheckboxMark($pdf, $x, $y, self::RESIDENT_SCALE);
                $matched = true;
                break;
            }
        }

        if (! $matched && $highestEducation) {
            $this->writeLineText($pdf, $highestEducation, 447, 914, 130, scale: self::RESIDENT_SCALE, size: 8.2);
        }

        if (in_array($normalizedStatus, ['graduate', 'completed', 'finished'], true)) {
            $this->drawCheckboxMark($pdf, 600, 908, self::RESIDENT_SCALE);
        }

        if (in_array($normalizedStatus, ['under graduate', 'undergraduate', 'under grad'], true)) {
            $this->drawCheckboxMark($pdf, 778, 908, self::RESIDENT_SCALE);
        }
    }

    private function residentAddress(Resident $resident): string
    {
        $parts = array_filter([
            $resident->household?->household_address,
            $resident->household?->purok?->display_name,
            $resident->household?->purok?->barangay?->name,
        ]);

        return implode(', ', $parts);
    }

    private function householdIndicatorSummary(Resident $resident): string
    {
        $profile = $resident->socioEconomicProfile;

        if (! $profile) {
            return '';
        }

        $markers = [];

        if ($profile->employment_status) {
            $markers[] = match (Str::lower($profile->employment_status)) {
                'employed' => 'Emp',
                'unemployed' => 'Unemp',
                'self-employed', 'self employed' => 'Self-Emp',
                default => $profile->employment_status,
            };
        }

        if ($profile->is_pwd) {
            $markers[] = 'PWD';
        }

        if ($profile->is_ofw) {
            $markers[] = 'OFW';
        }

        if ($profile->is_solo_parent) {
            $markers[] = 'Solo Parent';
        }

        if ($profile->is_osy) {
            $markers[] = 'OSY';
        }

        if ($profile->is_osc) {
            $markers[] = 'OSC';
        }

        if ($profile->is_ip) {
            $markers[] = 'IP';
        }

        return implode(', ', $markers);
    }

    private function resolveResidentAccomplishingParty(Resident $resident): ?string
    {
        return $resident->full_name
            ?: $this->resolveCreatedByName(Resident::class, $resident->id)
            ?: Auth::user()?->name;
    }

    private function resolveHouseholdPreparedBy(Household $household): ?string
    {
        return $household->headResident?->full_name
            ?: $this->resolveCreatedByName(Household::class, $household->id)
            ?: Auth::user()?->name;
    }

    private function resolveCreatedByName(string $modelType, int $modelId): ?string
    {
        return AuditLog::query()
            ->with('user:id,name')
            ->where('event_type', 'created')
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->oldest('created_at')
            ->first()?->user?->name;
    }

    private function formatAccomplishedDate(?CarbonInterface $timestamp): string
    {
        return ($timestamp ?? now())->format('m/d/Y');
    }

    private function writeBoxText(
        Fpdi $pdf,
        ?string $text,
        float $xPx,
        float $yPx,
        float $wPx,
        float $hPx,
        float $scale,
        float $size = 9,
        string $align = 'L',
        float $paddingX = 2.0
    ): void {
        $value = trim((string) $text);

        if ($value === '') {
            return;
        }

        $x = $this->scale($xPx, $scale);
        $y = $this->scale($yPx, $scale);
        $w = $this->scale($wPx, $scale);
        $h = $this->scale($hPx, $scale);
        $fontSize = $this->fitFontSize($pdf, $value, $w - ($paddingX * 2), $size);
        $lineHeight = min($h, $fontSize + 1.2);
        $verticalOffset = max(($h - $lineHeight) / 2, 0) - 0.2;

        $pdf->SetFont('Helvetica', '', $fontSize);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($x + $paddingX, $y + max($verticalOffset, 0));
        $pdf->Cell($w - ($paddingX * 2), $lineHeight, $value, 0, 0, $align);
    }

    private function writeLineText(
        Fpdi $pdf,
        ?string $text,
        float $xPx,
        float $yPx,
        float $wPx,
        float $scale,
        float $size = 9,
        string $align = 'L',
        float $lift = 1.8
    ): void {
        $value = trim((string) $text);

        if ($value === '') {
            return;
        }

        $x = $this->scale($xPx, $scale);
        $y = $this->scale($yPx, $scale);
        $w = $this->scale($wPx, $scale);
        $fontSize = $this->fitFontSize($pdf, $value, $w - 4, $size);
        $lineHeight = $fontSize + 0.8;

        $pdf->SetFont('Helvetica', '', $fontSize);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($x + 2, $y - $lineHeight - $lift);
        $pdf->Cell($w - 4, $lineHeight, $value, 0, 0, $align);
    }

    private function drawCheckboxMark(Fpdi $pdf, float $xPx, float $yPx, float $scale): void
    {
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($this->scale($xPx, $scale), $this->scale($yPx, $scale));
        $pdf->Cell($this->scale(25, $scale), $this->scale(24, $scale), 'X', 0, 0, 'C');
    }

    private function fitFontSize(Fpdi $pdf, string $text, float $width, float $startSize, float $minSize = 5.2): float
    {
        $size = $startSize;

        do {
            $pdf->SetFont('Helvetica', '', $size);

            if ($pdf->GetStringWidth($text) <= $width || $size <= $minSize) {
                return $size;
            }

            $size -= 0.2;
        } while ($size > $minSize);

        return $minSize;
    }

    private function scale(float $value, float $scale): float
    {
        return round($value * $scale, 2);
    }
}
