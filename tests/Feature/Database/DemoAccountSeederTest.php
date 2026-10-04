<?php

namespace Tests\Feature\Database;

use App\Models\Barangay;
use App\Models\User;
use Database\Seeders\BarangaySeeder;
use Database\Seeders\DemoAccountSeeder;
use Database\Seeders\MockOperationalDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoAccountSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_accounts_are_scoped_to_the_pooc_oriental_pilot_barangay(): void
    {
        $this->seed(BarangaySeeder::class);
        $this->seed(MockOperationalDataSeeder::class);
        // Explicitly replace the mock officeholder before assigning the main demo account.
        User::where('email', 'secretary.pooc-oriental@healthlink.test')->firstOrFail()->update(['is_active' => false]);
        $this->seed(DemoAccountSeeder::class);

        $pilotBarangay = Barangay::query()->where('name', 'Pooc Oriental')->firstOrFail();

        foreach (['secretary@healthlink.com', 'bns@healthlink.com'] as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertSame($pilotBarangay->id, $user->assigned_barangay_id);
            $this->assertNull($user->assigned_purok_id);
        }

        $bhw = User::query()
            ->with('assignedPurok')
            ->where('email', 'bhw@healthlink.com')
            ->firstOrFail();

        $this->assertSame($pilotBarangay->id, $bhw->assigned_barangay_id);
        $this->assertNotNull($bhw->assignedPurok);
        $this->assertSame($pilotBarangay->id, $bhw->assignedPurok->barangay_id);
        $this->assertSame(1, $bhw->assignedPurok->purok_number);

        foreach (['admin@healthlink.com', 'phn@healthlink.com', 'mho@healthlink.com'] as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertNull($user->assigned_barangay_id);
            $this->assertNull($user->assigned_purok_id);
        }
    }

    public function test_reseeding_updates_demo_accounts_without_creating_duplicates(): void
    {
        $this->seed(BarangaySeeder::class);
        $this->seed(MockOperationalDataSeeder::class);
        User::where('email', 'secretary.pooc-oriental@healthlink.test')->firstOrFail()->update(['is_active' => false]);
        $this->seed(DemoAccountSeeder::class);

        $demoEmails = [
            'admin@healthlink.com',
            'phn@healthlink.com',
            'mho@healthlink.com',
            'secretary@healthlink.com',
            'bns@healthlink.com',
            'bhw@healthlink.com',
        ];
        $originalIds = User::query()
            ->whereIn('email', $demoEmails)
            ->pluck('id', 'email')
            ->all();

        $this->seed(DemoAccountSeeder::class);

        $this->assertCount(6, $originalIds);
        $this->assertSame(
            $originalIds,
            User::query()->whereIn('email', $demoEmails)->pluck('id', 'email')->all()
        );
    }

    public function test_combined_legacy_demo_seeders_cannot_silently_replace_the_existing_secretary(): void
    {
        $this->seed(BarangaySeeder::class);
        $this->seed(MockOperationalDataSeeder::class);
        $existing = User::where('email', 'secretary.pooc-oriental@healthlink.test')->firstOrFail();
        try {
            $this->seed(DemoAccountSeeder::class);
            $this->fail('A second operational demo Secretary must be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('assigned_barangay_id', $exception->errors());
        }
        $this->assertTrue($existing->fresh()->is_active);
        $this->assertSame(1, User::operationalSecretaries()->where('assigned_barangay_id', $existing->assigned_barangay_id)->count());
        $this->assertDatabaseMissing('users', ['email' => 'secretary@healthlink.com']);
    }
}
