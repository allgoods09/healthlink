<?php

namespace Tests\Unit;

use App\Support\Nutrition\OptCycleRules;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OptCycleEligibilityTest extends TestCase
{
    public static function birthdays(): array
    {
        return [
            'newborn' => ['2026-07-01', '2026-07-01', true],
            '59 months and days' => ['2021-07-02', '2026-07-01', true],
            'fifth birthday' => ['2021-07-01', '2026-07-01', false],
            'older child' => ['2020-07-01', '2026-07-01', false],
            'future DOB' => ['2026-07-02', '2026-07-01', false],
            'missing DOB' => [null, '2026-07-01', false],
            'invalid DOB' => ['2026-02-30', '2026-07-01', false],
            'leap day before fifth birthday' => ['2020-02-29', '2025-02-27', true],
            'leap day fifth birthday convention' => ['2020-02-29', '2025-02-28', false],
        ];
    }

    #[DataProvider('birthdays')]
    public function test_pilot_eligibility(?string $dob, string $reference, bool $eligible): void
    {
        $this->assertSame($eligible, OptCycleRules::eligibleDob($dob, CarbonImmutable::parse($reference)));
    }
}
