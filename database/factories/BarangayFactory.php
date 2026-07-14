<?php

namespace Database\Factories;

use App\Models\Barangay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Barangay>
 */
class BarangayFactory extends Factory
{
    protected $model = Barangay::class;

    private const BARANGAY_NAMES = [
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
        'Villanueva',
    ];

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(self::BARANGAY_NAMES),
            'psgc_code' => fake()->unique()->numerify('071242###'),
            'municipality' => 'Tubigon',
            'province' => 'Bohol',
            'region' => 'VII',
            'is_active' => true,
        ];
    }
}
