<?php

namespace Database\Factories;

use App\Models\Backup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Backup>
 */
class BackupFactory extends Factory
{
    protected $model = Backup::class;

    public function definition(): array
    {
        $timestamp = now()->format('Ymd_His_u');
        $filename = "backup_full_{$timestamp}.sql";

        return [
            'filename' => $filename,
            'file_path' => "backups/{$filename}",
            'file_size' => 2048,
            'backup_type' => 'full',
            'status' => Backup::STATUS_COMPLETED,
            'generated_by' => User::factory(),
            'storage_location' => Backup::STORAGE_LOCAL,
            'notes' => fake()->optional()->sentence(),
            'metadata' => [
                'integrity_status' => 'unverified',
            ],
            'expires_at' => now()->addDays(30),
        ];
    }
}
