<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Barangay;

class BarangaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $barangays = [
            'BagongBanwa Island',
            'Banlasan',
            'Batasan Island',
            'BilangBilangan Island',
            'Bosongon',
            'Buenos Aires',
            'Bunacan',
            'Cabulijan',
            'Cahayag',
            'Cawayanan',
            'Centro',
            'Genonocan',
            'Guiwanon',
            'Ilijan Norte',
            'Ilijan Sur',
            'Libertad',
            'Macaas',
            'Matabao',
            'Mocaboc Island',
            'Panadtaran',
            'Panaytayon',
            'Pandan',
            'Pangapasan Island',
            'Pinayagan Sur',
            'Pinayagan Norte',
            'Pooc Occidental',
            'Pooc Oriental',
            'Potohan',
            'Talenceras',
            'Tan-awan',
            'Tinangnan',
            'Ubojan',
            'Ubay Island',
            'Villanueva'
        ];

        foreach ($barangays as $index => $name) {
            // Generate PSGC code (simplified - you might want to use actual PSGC codes)
            $psgcCode = $this->generatePsgcCode($index + 1);
            
            Barangay::updateOrCreate(
                ['name' => $name],
                [
                    'psgc_code' => $psgcCode,
                    'municipality' => 'Tubigon',
                    'province' => 'Bohol',
                    'region' => 'VII',
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Generate a simplified PSGC code for Tubigon barangays.
     * Format: 071242XXX (where XXX is the barangay number)
     * 
     * Note: You should replace this with actual PSGC codes from PSA
     */
    private function generatePsgcCode(int $number): string
    {
        // Pad the number to 3 digits (e.g., 1 → 001, 34 → 034)
        $paddedNumber = str_pad($number, 3, '0', STR_PAD_LEFT);
        return '071242' . $paddedNumber;
    }
}