<?php

namespace Tests\Support;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;

trait LifecycleResidentFixture
{
    private function residentFixture(array $overrides = []): Resident
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id, 'purok_number' => 1]);
        $household = Household::create(['purok_id' => $purok->id, 'household_no' => '001',
            'household_address' => 'Synthetic fixture address', 'is_active' => true]);

        return Resident::create(array_merge([
            'household_id' => $household->id, 'first_name' => 'Fixture', 'last_name' => 'Resident',
            'birth_date' => '1994-03-12', 'birth_place' => 'Tubigon', 'sex' => 'Female',
            'civil_status' => 'Single', 'citizenship' => 'Filipino', 'relationship_to_head' => 'Head',
            'resident_status' => 'active', 'is_active' => true,
            // Explicit codes keep these tests independent of the unchanged legacy allocator.
            'official_resident_code' => sprintf('RS-%04d-00001', $barangay->id),
        ], $overrides));
    }
}
