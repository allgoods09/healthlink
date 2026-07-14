<?php

namespace Database\Factories;

use App\Models\Barangay;
use App\Models\Purok;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purok>
 */
class PurokFactory extends Factory
{
    protected $model = Purok::class;

    private const PUROK_NAMES = [
        1 => 'Purok Centro',
        2 => 'Purok Baybay',
        3 => 'Purok Ilaya',
        4 => 'Purok Luyo',
        5 => 'Purok Riverside',
        6 => 'Purok Crossing',
        7 => 'Purok Proper',
    ];

    public function definition(): array
    {
        $purokNumber = fake()->numberBetween(1, 7);

        return [
            'barangay_id' => Barangay::factory(),
            'purok_number' => $purokNumber,
            'purok_name' => self::PUROK_NAMES[$purokNumber] ?? 'Purok '.$purokNumber,
            'is_active' => true,
        ];
    }
}
