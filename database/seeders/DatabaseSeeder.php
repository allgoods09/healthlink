<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Barangay;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            BarangaySeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Cristino Algodon',
            'email' => 'example@healthlink.com',
            'password' => bcrypt('password'), // Replace with a secure password
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
