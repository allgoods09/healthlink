<?php

namespace Tests\Feature\Database;

use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CurrentPopulationTest extends TestCase
{
    use RefreshDatabase;

    private int $memberSequence = 0;

    #[DataProvider('populationStates')]
    public function test_query_and_instance_predicates_follow_lifecycle_not_availability(
        string $status,
        bool $isActive,
        bool $deleted,
        bool $expected,
    ): void {
        $household = $this->householdFixture();
        $resident = $this->memberFixture($household, ['resident_status' => $status, 'is_active' => $isActive]);
        if ($deleted) {
            $resident->delete();
        }
        $resident = Resident::withTrashed()->findOrFail($resident->id);

        $this->assertSame($expected, $resident->isCurrentPopulation());
        $this->assertSame($expected, Resident::currentPopulation()->whereKey($resident->id)->exists());
        $this->assertSame($expected ? 1 : 0, $household->currentMemberCount());
        $this->assertSame(! $expected, $household->isVacant());
        $this->assertSame($isActive && $status === Resident::STATUS_ACTIVE && ! $deleted, $resident->isActive());
    }

    public static function populationStates(): array
    {
        $states = [];
        foreach ([Resident::STATUS_ACTIVE, Resident::STATUS_DECEASED, Resident::STATUS_RELOCATED, Resident::STATUS_MOVED_OUT] as $status) {
            foreach ([true, false] as $isActive) {
                $states[$status.' / '.($isActive ? 'available' : 'unavailable')] = [
                    $status, $isActive, false, $status === Resident::STATUS_ACTIVE,
                ];
            }
        }
        foreach ([true, false] as $isActive) {
            $states['soft-deleted active / '.($isActive ? 'available' : 'unavailable')] = [
                Resident::STATUS_ACTIVE, $isActive, true, false,
            ];
        }

        return $states;
    }

    public function test_with_trashed_in_either_order_cannot_broaden_current_population(): void
    {
        $household = $this->householdFixture();
        $current = $this->memberFixture($household, ['is_active' => false]);
        $deleted = $this->memberFixture($household);
        $deleted->delete();

        $this->assertSame([$current->id], Resident::withTrashed()->currentPopulation()->pluck('id')->all());
        $this->assertSame([$current->id], Resident::currentPopulation()->withTrashed()->pluck('id')->all());
        $this->assertSame([$current->id], $household->currentMembers()->withTrashed()->pluck('id')->all());
        $this->assertSame(0, Resident::onlyTrashed()->currentPopulation()->count());
    }

    public function test_mixed_members_preserve_broad_relationships_and_canonical_eager_counts(): void
    {
        $household = $this->householdFixture();
        $current = $this->memberFixture($household);
        $unavailable = $this->memberFixture($household, ['is_active' => false]);
        $deceased = $this->memberFixture($household, ['resident_status' => Resident::STATUS_DECEASED]);
        $relocated = $this->memberFixture($household, ['resident_status' => Resident::STATUS_RELOCATED]);
        $movedOut = $this->memberFixture($household, ['resident_status' => Resident::STATUS_MOVED_OUT]);
        $deleted = $this->memberFixture($household);
        $deleted->delete();
        $otherHousehold = $this->householdFixture();
        $this->memberFixture($otherHousehold);

        $loaded = Household::with(['residents', 'currentMembers'])->withCount('currentMembers')->findOrFail($household->id);
        $this->assertSame([$current->id, $unavailable->id], $loaded->currentMembers->sortBy('id')->pluck('id')->all());
        $this->assertSame([$current->id, $unavailable->id, $deceased->id, $relocated->id, $movedOut->id], $loaded->residents->sortBy('id')->pluck('id')->all());
        $this->assertSame(6, $loaded->residents()->withTrashed()->count());
        $this->assertSame(2, $loaded->currentMemberCount());
        $this->assertSame(2, $loaded->current_members_count);
        $this->assertSame(5, $loaded->resident_count);
        $this->assertFalse($loaded->isVacant());
        $this->assertSame([$household->id, $otherHousehold->id], Household::whereHas('currentMembers')->orderBy('id')->pluck('id')->all());
    }

    public function test_empty_households_are_vacant_regardless_of_legacy_availability(): void
    {
        foreach ([true, false] as $isActive) {
            $household = $this->householdFixture(['is_active' => $isActive]);
            $this->assertSame(0, $household->currentMemberCount());
            $this->assertTrue($household->isVacant());
            $this->assertSame(0, Household::withCount('currentMembers')->findOrFail($household->id)->current_members_count);
            $this->assertSame($isActive, $household->isActive());
        }
    }

    public function test_legacy_household_availability_does_not_change_occupancy(): void
    {
        $household = $this->householdFixture(['is_active' => false]);
        $this->memberFixture($household, ['is_active' => false]);

        $this->assertFalse($household->isActive());
        $this->assertFalse($household->isVacant());
        $this->assertSame(1, $household->currentMemberCount());
    }

    public function test_noncurrent_stored_head_remains_attached_and_is_not_repaired(): void
    {
        $household = $this->householdFixture();
        $head = $this->memberFixture($household, ['resident_status' => Resident::STATUS_RELOCATED]);
        $household->update(['head_resident_id' => $head->id]);

        $this->assertTrue($household->isVacant());
        $this->assertSame(0, $household->currentMemberCount());
        $this->assertSame($head->id, $household->headResident->id);
        $this->assertSame($head->id, $household->fresh()->head_resident_id);
        $this->assertSame($head->id, $household->residents()->sole()->id);
        $this->assertSame('Head', $head->fresh()->relationship_to_head);
    }

    public function test_legacy_active_scope_and_is_active_predicate_remain_unchanged(): void
    {
        $household = $this->householdFixture();
        $currentButUnavailable = $this->memberFixture($household, ['is_active' => false]);
        $deceasedButAvailable = $this->memberFixture($household, ['resident_status' => Resident::STATUS_DECEASED]);

        $this->assertSame([$deceasedButAvailable->id], Resident::active()->pluck('id')->all());
        $this->assertFalse($currentButUnavailable->isActive());
        $this->assertFalse($deceasedButAvailable->isActive());
        $this->assertSame([$currentButUnavailable->id], Resident::currentPopulation()->pluck('id')->all());
        $this->assertSame(2, Resident::count());
    }

    public function test_canonical_columns_are_qualified_for_joined_queries(): void
    {
        $household = $this->householdFixture(['is_active' => false]);
        $current = $this->memberFixture($household, ['is_active' => false]);
        $deleted = $this->memberFixture($household);
        $deleted->delete();
        $query = Resident::withTrashed()->currentPopulation()
            ->join('households', 'households.id', '=', 'residents.household_id');

        $this->assertStringContainsString('`residents`.`resident_status`', $query->toSql());
        $this->assertStringContainsString('`residents`.`deleted_at` is null', $query->toSql());
        $this->assertSame([$current->id], $query->pluck('residents.id')->all());
    }

    public function test_reading_population_and_membership_does_not_mutate_registry_or_history(): void
    {
        $household = $this->householdFixture();
        $head = $this->memberFixture($household, ['resident_status' => Resident::STATUS_DECEASED]);
        $this->memberFixture($household, ['is_active' => false]);
        $deleted = $this->memberFixture($household);
        $deleted->delete();
        $household->update(['head_resident_id' => $head->id]);
        $before = $this->registrySnapshot();

        Resident::withTrashed()->currentPopulation()->get();
        foreach (Resident::withTrashed()->get() as $resident) {
            $resident->isCurrentPopulation();
        }
        foreach (Household::with(['currentMembers', 'residents', 'headResident'])->withCount('currentMembers')->get() as $record) {
            $record->currentMemberCount();
            $record->isVacant();
        }

        $this->assertSame($before, $this->registrySnapshot());
    }

    private function householdFixture(array $overrides = []): Household
    {
        $purok = Purok::factory()->create(['purok_number' => 1]);

        return Household::create(array_merge([
            'purok_id' => $purok->id,
            'household_no' => '001',
            'household_address' => 'Synthetic fixture address',
            'is_active' => true,
        ], $overrides));
    }

    private function memberFixture(Household $household, array $overrides = []): Resident
    {
        return Resident::create(array_merge([
            'household_id' => $household->id,
            'first_name' => 'Fixture '.++$this->memberSequence,
            'last_name' => 'Resident',
            'birth_date' => '1994-03-12',
            'birth_place' => 'Tubigon',
            'sex' => 'Female',
            'civil_status' => 'Single',
            'citizenship' => 'Filipino',
            'relationship_to_head' => 'Head',
            'resident_status' => Resident::STATUS_ACTIVE,
            'is_active' => true,
        ], $overrides))->fresh();
    }

    private function registrySnapshot(): array
    {
        $snapshot = [];
        foreach (['residents', 'households', 'resident_lifecycle_events', 'resident_code_sequences', 'archived_records', 'audit_logs'] as $table) {
            $snapshot[$table] = DB::table($table)
                ->orderBy($table === 'resident_code_sequences' ? 'origin_barangay_id' : 'id')
                ->get()->toJson();
        }

        return $snapshot;
    }
}
