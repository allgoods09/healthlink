<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\Purok;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountSeeder extends Seeder
{
    private const PILOT_BARANGAY_NAME = 'Pooc Oriental';

    private const PILOT_BHW_PUROK_NUMBER = 1;

    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@healthlink.com')->firstOrFail();
        $pilotBarangay = Barangay::query()
            ->where('name', self::PILOT_BARANGAY_NAME)
            ->firstOrFail();
        $pilotPurok = Purok::query()
            ->whereBelongsTo($pilotBarangay)
            ->where('purok_number', self::PILOT_BHW_PUROK_NUMBER)
            ->firstOrFail();

        $this->upsertScopedDemoUser($admin, [
            'name' => 'Tubigon Demo Secretary',
            'email' => 'secretary@healthlink.com',
            'role' => 'secretary',
            'assigned_barangay_id' => $pilotBarangay->id,
            'assigned_purok_id' => null,
        ]);

        $this->upsertScopedDemoUser($admin, [
            'name' => 'Tubigon Demo BNS',
            'email' => 'bns@healthlink.com',
            'role' => 'bns',
            'assigned_barangay_id' => $pilotBarangay->id,
            'assigned_purok_id' => null,
        ]);

        $this->upsertScopedDemoUser($admin, [
            'name' => 'Tubigon Demo BHW',
            'email' => 'bhw@healthlink.com',
            'role' => 'bhw',
            'assigned_barangay_id' => $pilotBarangay->id,
            'assigned_purok_id' => $pilotPurok->id,
        ]);
    }

    private function upsertScopedDemoUser(User $admin, array $attributes): void
    {
        User::query()->updateOrCreate(
            ['email' => $attributes['email']],
            [
                'name' => $attributes['name'],
                'password' => Hash::make('password'),
                'role' => $attributes['role'],
                'approval_status' => User::APPROVAL_APPROVED,
                'registered_via' => 'seed',
                'requested_role' => null,
                'assigned_barangay_id' => $attributes['assigned_barangay_id'],
                'assigned_purok_id' => $attributes['assigned_purok_id'],
                'requested_barangay_id' => null,
                'requested_purok_id' => null,
                'approval_notes' => 'Seeded Tubigon demo account',
                'email_verified_at' => now(),
                'approved_at' => now(),
                'approved_by' => $admin->id,
                'rejected_at' => null,
                'rejected_by' => null,
                'is_active' => true,
            ]
        );
    }
}
