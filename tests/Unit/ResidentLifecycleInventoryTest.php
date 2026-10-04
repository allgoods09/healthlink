<?php

namespace Tests\Unit;

use App\Support\ResidentLifecycleInventory as Inventory;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ResidentLifecycleInventoryTest extends TestCase
{
    #[DataProvider('observations')]
    public function test_legacy_observations_are_diagnostic_not_status_rewrites(array $overrides, array $expected): void
    {
        $row = array_merge(['resident_status' => 'active', 'is_active' => true, 'deleted_at' => null,
            'birth_date' => '2000-01-01', 'date_of_death' => null, 'moved_out_at' => null], $overrides);
        $before = $row;
        $this->assertSame($expected, Inventory::classifyResident($row, CarbonImmutable::parse('2026-10-05')));
        $this->assertSame($before, $row);
        $this->assertNull(Inventory::statusIssue($row['resident_status']));
    }

    public static function observations(): array
    {
        return [
            'normal active' => [[], []],
            'archive' => [['deleted_at' => '2026-10-01'], ['soft_deleted']],
            'inactive active' => [['is_active' => false], ['active_status_but_inactive']],
            'valid deceased' => [['resident_status' => 'deceased', 'is_active' => false, 'date_of_death' => '2026-01-01'], []],
            'deceased active missing date' => [['resident_status' => 'deceased'], ['deceased_but_active', 'deceased_without_valid_date_of_death']],
            'legacy relocated' => [['resident_status' => 'relocated', 'is_active' => false], ['legacy_relocated_requires_review']],
            'legacy relocated active' => [['resident_status' => 'relocated'], ['legacy_relocated_requires_review', 'relocated_but_active']],
            'valid moved out' => [['resident_status' => 'moved_out', 'is_active' => false, 'moved_out_at' => '2026-01-01'], []],
            'moved out active missing date' => [['resident_status' => 'moved_out'], ['moved_out_but_active', 'moved_out_without_valid_moved_out_at']],
            'active death' => [['date_of_death' => '2026-01-01'], ['active_with_date_of_death']],
            'active move' => [['moved_out_at' => '2026-01-01'], ['active_with_moved_out_at']],
            'future death' => [['date_of_death' => '2027-01-01'], ['invalid_date_of_death', 'active_with_date_of_death']],
            'move before birth' => [['moved_out_at' => '1999-12-31'], ['invalid_moved_out_at', 'active_with_moved_out_at']],
            'invalid calendar date' => [['date_of_death' => '2025-02-29'], ['invalid_date_of_death', 'active_with_date_of_death']],
            'mixed dates' => [['resident_status' => 'deceased', 'is_active' => false, 'date_of_death' => '2026-01-01', 'moved_out_at' => '2025-01-01'], ['mixed_lifecycle_dates']],
        ];
    }

    public function test_identity_checks_distinguish_unsupported_statuses_from_compatible_legacy_values(): void
    {
        foreach ([null, '', 'unknown', 'ACTIVE'] as $value) {
            $this->assertSame('unsupported_status', Inventory::statusIssue($value));
        }
        foreach (Inventory::STATUSES as $value) {
            $this->assertNull(Inventory::statusIssue($value));
        }
        $this->assertSame('null_code', Inventory::codeIssue(null));
        $this->assertSame('empty_code', Inventory::codeIssue('  '));
        foreach (['legacy-1', 'RS-27-00001', 'RS-0027-00001\n'] as $value) {
            $this->assertSame('nonstandard_code', Inventory::codeIssue($value));
        }
        foreach (['RS-0027-00001', 'RS-10000-100000'] as $value) {
            $this->assertNull(Inventory::codeIssue($value));
        }
    }

    public function test_missing_mandatory_ownership_is_a_blocker_but_an_existing_chain_is_compatible(): void
    {
        $this->assertSame([], Inventory::ownershipIssues(['household' => 1, 'purok' => 2, 'barangay' => 3]));
        $this->assertSame(['missing_purok', 'missing_barangay'], Inventory::ownershipIssues(['household' => 1]));
        $this->assertSame(['missing_household', 'missing_purok', 'missing_barangay'], Inventory::ownershipIssues([]));
    }
}
