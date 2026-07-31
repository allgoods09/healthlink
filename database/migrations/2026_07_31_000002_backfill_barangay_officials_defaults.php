<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            ['role_key' => 'punong_barangay', 'official_title' => 'Punong Barangay', 'display_order' => 1],
            ['role_key' => 'barangay_secretary', 'official_title' => 'Barangay Secretary', 'display_order' => 2],
            ['role_key' => 'barangay_treasurer', 'official_title' => 'Barangay Treasurer', 'display_order' => 3],
            ['role_key' => 'kagawad_1', 'official_title' => 'Barangay Kagawad 1', 'display_order' => 4],
            ['role_key' => 'kagawad_2', 'official_title' => 'Barangay Kagawad 2', 'display_order' => 5],
            ['role_key' => 'kagawad_3', 'official_title' => 'Barangay Kagawad 3', 'display_order' => 6],
            ['role_key' => 'kagawad_4', 'official_title' => 'Barangay Kagawad 4', 'display_order' => 7],
            ['role_key' => 'kagawad_5', 'official_title' => 'Barangay Kagawad 5', 'display_order' => 8],
            ['role_key' => 'kagawad_6', 'official_title' => 'Barangay Kagawad 6', 'display_order' => 9],
            ['role_key' => 'kagawad_7', 'official_title' => 'Barangay Kagawad 7', 'display_order' => 10],
        ];

        DB::table('barangays')
            ->select('id')
            ->orderBy('id')
            ->get()
            ->each(function (object $barangay) use ($defaults): void {
                foreach ($defaults as $definition) {
                    DB::table('barangay_officials')->updateOrInsert(
                        [
                            'barangay_id' => $barangay->id,
                            'role_key' => $definition['role_key'],
                        ],
                        [
                            'official_title' => $definition['official_title'],
                            'display_order' => $definition['display_order'],
                            'is_active' => true,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        // Keep existing official rows intact on rollback to avoid destroying live barangay metadata.
    }
};
