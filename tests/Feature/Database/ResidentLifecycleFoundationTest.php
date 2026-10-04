<?php

namespace Tests\Feature\Database;

use App\Http\Requests\Admin\Geometry\ResidentStoreRequest;
use App\Http\Requests\Admin\Geometry\ResidentUpdateRequest;
use App\Http\Requests\Bhw\StoreResidentUpdateRequest;
use App\Models\User;
use App\Support\ResidentLifecycleInventory as Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\Support\LifecycleResidentFixture;
use Tests\TestCase;

class ResidentLifecycleFoundationTest extends TestCase
{
    use LifecycleResidentFixture, RefreshDatabase;

    public function test_mariadb_schema_preserves_constraints_and_adds_only_compatible_capability(): void
    {
        $this->assertSame('mysql', Schema::getConnection()->getDriverName());
        $columns = Schema::getColumns('residents');
        $indexes = Schema::getIndexes('residents');
        $foreignKeys = Schema::getForeignKeys('residents');
        $this->assertSame([], Inventory::schemaIssues($columns, $indexes, $foreignKeys));
        $byName = array_column($columns, null, 'name');
        $this->assertSame("enum('active','deceased','relocated','moved_out')", $byName['resident_status']['type']);
        $this->assertSame('active', trim($byName['resident_status']['default'], "'\""));
        $this->assertFalse($byName['resident_status']['nullable']);
        $version = $byName['lifecycle_version'];
        $this->assertStringContainsString('bigint', $version['type']);
        $this->assertStringContainsString('unsigned', $version['type']);
        $this->assertFalse($version['nullable']);
        $this->assertSame('0', trim((string) $version['default'], "'\""));
        foreach ($indexes as $index) {
            $this->assertNotContains('lifecycle_version', $index['columns']);
        }
        $this->assertNotEmpty(Inventory::schemaIssues($columns, [], $foreignKeys));
        $this->assertContains('household_foreign_key_mismatch', array_column(Inventory::schemaIssues($columns, $indexes, []), 'reason'));
        $byName['lifecycle_version']['nullable'] = true;
        $this->assertContains('lifecycle_version_schema_mismatch', array_column(Inventory::schemaIssues(array_values($byName), $indexes, $foreignKeys), 'reason'));
    }

    public function test_existing_helpers_and_availability_remain_unchanged(): void
    {
        foreach (['active' => 'Active Resident', 'deceased' => 'Deceased', 'relocated' => 'Relocated', 'moved_out' => 'Moved Out'] as $status => $label) {
            $resident = $this->residentFixture(['resident_status' => $status])->fresh();
            $this->assertSame($label, $resident->resident_status_label);
            $this->assertSame($status === 'active', $resident->isActive());
            $this->assertSame($status === 'deceased', $resident->isDeceased());
            $this->assertSame($status === 'relocated', $resident->isRelocated());
            $this->assertSame($status === 'moved_out', $resident->isMovedOut());
            $this->assertTrue($resident->is_active);
        }
        $resident = $this->residentFixture(['is_active' => false])->fresh();
        $this->assertFalse($resident->isActive());
        $this->assertSame('active', $resident->resident_status);
    }

    public function test_version_is_cast_hidden_not_fillable_and_not_incremented_by_existing_crud(): void
    {
        $resident = $this->residentFixture()->fresh();
        $this->assertSame(0, $resident->lifecycle_version);
        $this->assertFalse($resident->isFillable('lifecycle_version'));
        $this->assertArrayNotHasKey('lifecycle_version', $resident->toArray());
        $resident->update(['occupation' => 'Vendor']);
        $this->assertSame(0, $resident->fresh()->lifecycle_version);
        $secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $resident->household->purok->barangay_id]);
        $this->actingAs($secretary)->patch(route('secretary.residents.toggle-status', $resident), ['lifecycle_version' => 99])->assertRedirect();
        $this->assertSame(0, $resident->fresh()->lifecycle_version);
        $this->assertFalse($resident->fresh()->is_active);
        $this->assertSame('active', $resident->fresh()->resident_status);
    }

    public function test_current_registry_and_correction_allowlists_do_not_activate_moved_out(): void
    {
        foreach ([ResidentStoreRequest::class,
            ResidentUpdateRequest::class,
            StoreResidentUpdateRequest::class,
            \App\Http\Requests\Phn\StoreResidentUpdateRequest::class] as $requestClass) {
            $rules = (new $requestClass)->rules();
            $this->assertTrue(Validator::make(['resident_status' => 'moved_out'], ['resident_status' => $rules['resident_status']])->fails());
            foreach (['active', 'deceased', 'relocated'] as $status) {
                $this->assertFalse(Validator::make(['resident_status' => $status], ['resident_status' => $rules['resident_status']])->fails());
            }
            $this->assertArrayNotHasKey('lifecycle_version', $rules);
        }
    }
}
