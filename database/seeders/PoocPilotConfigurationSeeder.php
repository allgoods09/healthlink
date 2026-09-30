<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\Purok;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

// Explicitly restore the seven already-configured HealthLink Pooc puroks in a CLEAN development database.
class PoocPilotConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Pilot configuration seeding is development/testing only.');
        }
        DB::transaction(function (): void {
            $this->call(BarangaySeeder::class);
            $pilot = Barangay::where('name', config('pooc_population.barangay'))->firstOrFail();
            if (Purok::withTrashed()->where('barangay_id', '!=', $pilot->id)->exists()) {
                throw new RuntimeException('Non-pilot puroks exist; no records were changed.');
            }
            if (DB::table('users')->whereIn('role', ['secretary', 'bns', 'bhw'])
                ->where(fn ($q) => $q->whereNull('assigned_barangay_id')->orWhere('assigned_barangay_id', '!=', $pilot->id))->exists()) {
                throw new RuntimeException('Non-pilot frontline accounts exist; use an isolated clean database. No accounts were changed.');
            }
            foreach (config('pooc_population.configured_puroks') as $number => $name) {
                Purok::firstOrCreate(['barangay_id' => $pilot->id, 'purok_number' => $number], ['purok_name' => $name, 'is_active' => true]);
            }
            $hash = Hash::make('password');
            foreach (['admin', 'phn', 'mho', 'secretary', 'bns', 'bhw'] as $role) {
                $barangay = in_array($role, ['secretary', 'bns', 'bhw']) ? $pilot->id : null;
                $purok = $role === 'bhw' ? $pilot->puroks()->where('purok_number', 1)->firstOrFail()->id : null;
                $existing = User::withTrashed()->where('email', $role.'@healthlink.com')->first();
                if ($existing && ($existing->role !== $role || $existing->assigned_barangay_id !== $barangay || $existing->assigned_purok_id !== $purok || $existing->trashed())) {
                    throw new RuntimeException('Conflicting existing demo account; no accounts will be reassigned.');
                }
                User::firstOrCreate(['email' => $role.'@healthlink.com'], [
                    'name' => 'Synthetic Demo '.ucfirst($role), 'role' => $role, 'password' => $hash,
                    'assigned_barangay_id' => $barangay, 'assigned_purok_id' => $purok, 'registered_via' => 'seed',
                    'approval_status' => User::APPROVAL_APPROVED, 'is_active' => true, 'email_verified_at' => now(),
                ]);
            }
        });
    }
}
