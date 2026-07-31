<?php

namespace App\Support;

use App\Models\Barangay;
use App\Models\BarangayOfficial;
use Illuminate\Support\Collection;

class BarangayOfficialsRegistry
{
    /**
     * Ensure the standard official slots exist for the barangay.
     */
    public function syncDefaults(Barangay $barangay): Collection
    {
        foreach (BarangayOfficial::defaults() as $definition) {
            BarangayOfficial::query()->updateOrCreate(
                [
                    'barangay_id' => $barangay->id,
                    'role_key' => $definition['role_key'],
                ],
                [
                    'official_title' => $definition['official_title'],
                    'display_order' => $definition['display_order'],
                    'is_active' => true,
                ]
            );
        }

        return $barangay->officials()->get();
    }

    /**
     * Get the officials keyed by role for quick lookup.
     */
    public function keyed(Barangay $barangay): Collection
    {
        return $this->syncDefaults($barangay)->keyBy('role_key');
    }

    public function secretaryName(Barangay $barangay): ?string
    {
        return $this->keyed($barangay)->get(BarangayOfficial::ROLE_BARANGAY_SECRETARY)?->official_name;
    }

    public function punongBarangayName(Barangay $barangay): ?string
    {
        return $this->keyed($barangay)->get(BarangayOfficial::ROLE_PUNONG_BARANGAY)?->official_name;
    }
}
