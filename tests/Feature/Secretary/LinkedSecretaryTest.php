<?php

namespace Tests\Feature\Secretary;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Support\BarangayOfficialsRegistry;
use App\Support\RbiTemplatePdfGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkedSecretaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_fields_display_account_name_and_preserve_fallback_across_all_save_paths(): void
    {
        [$secretary, $barangay] = $this->fixture();
        $admin = User::factory()->create(['role' => 'admin']);
        $names = $barangay->officials()->pluck('official_name', 'role_key')->all();
        $names['barangay_secretary'] = ['Forged malformed submission'];
        $names['punong_barangay'] = 'Changed Captain';
        $this->actingAs($secretary)->get(route('secretary.officials.index'))->assertOk()
            ->assertSee($secretary->display_name)->assertSee('System-linked')->assertDontSee('Manual Secretary');
        $edit = $this->get(route('secretary.officials.edit'))->assertOk();
        $this->assertLinkedInput($edit->getContent(), 'official_barangay_secretary', $secretary->display_name);
        $this->put(route('secretary.documents.officials.update'), ['officials' => $names])->assertSessionHasNoErrors();
        $this->assertFallback($barangay);
        unset($names['barangay_secretary']);
        $this->put(route('secretary.documents.officials.update'), ['officials' => $names])->assertSessionHasNoErrors();
        $this->assertFallback($barangay);
        $edit = $this->actingAs($admin)->get(route('admin.documents.index', ['barangay_id' => $barangay->id]))->assertOk();
        $this->assertLinkedInput($edit->getContent(), 'official_barangay_secretary', $secretary->display_name);
        $names['barangay_secretary'] = ['Forged malformed submission'];
        $this->put(route('admin.documents.officials.update'), ['barangay_id' => $barangay->id, 'officials' => $names])
            ->assertSessionHasNoErrors();
        $this->assertFallback($barangay);
        $edit = $this->get(route('admin.barangays.edit', $barangay))->assertOk();
        $this->assertLinkedInput($edit->getContent(), 'official_names_barangay_secretary', $secretary->display_name);
        $this->put(route('admin.barangays.update', $barangay), [
            'name' => $barangay->name, 'psgc_code' => $barangay->psgc_code, 'is_active' => true,
            'official_names' => $names,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertFallback($barangay);
        $this->assertSame('Changed Captain', $barangay->officials()->where('role_key', 'punong_barangay')->value('official_name'));
    }

    public function test_linked_old_input_cannot_replace_account_name_when_other_fields_fail_validation(): void
    {
        [$secretary, $barangay] = $this->fixture();
        $this->actingAs($secretary)->from(route('secretary.officials.edit'))->followingRedirects()
            ->put(route('secretary.documents.officials.update'), ['officials' => [
                'barangay_secretary' => 'Forged Old Input', 'punong_barangay' => str_repeat('X', 151),
            ]])->assertOk()->assertSee('value="'.$secretary->display_name.'"', false)
            ->assertDontSee('value="Forged Old Input"', false)->assertSee('value="'.str_repeat('X', 151).'"', false);
        $this->assertFallback($barangay);
    }

    public function test_manual_field_becomes_editable_again_and_missing_fallback_is_not_recreated_by_resolution(): void
    {
        [$secretary, $barangay] = $this->fixture();
        $secretary->update(['is_active' => false]);
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get(route('admin.documents.index', ['barangay_id' => $barangay->id]))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $input = $document->getElementById('official_barangay_secretary');
        $this->assertFalse($input->hasAttribute('disabled'));
        $this->assertSame('Manual Secretary', $input->getAttribute('value'));
        $this->put(route('admin.documents.officials.update'), ['barangay_id' => $barangay->id,
            'officials' => ['punong_barangay' => 'Captain']])->assertSessionHasNoErrors();
        $this->assertFallback($barangay);
        $this->put(route('admin.documents.officials.update'), ['barangay_id' => $barangay->id,
            'officials' => ['barangay_secretary' => 'New Manual Secretary', 'punong_barangay' => 'Captain']])->assertSessionHasNoErrors();
        $registry = app(BarangayOfficialsRegistry::class);
        $this->assertSame('New Manual Secretary', $registry->resolvedSecretaryName($barangay));
        $barangay->officials()->where('role_key', 'barangay_secretary')->delete();
        $count = $barangay->officials()->count();
        $this->assertNull($registry->resolvedSecretaryName($barangay));
        $this->assertSame($count, $barangay->officials()->count());
    }

    public function test_admin_rbi_entry_points_use_officeholder_not_admin_and_preserve_captain_and_audit_actor(): void
    {
        [$secretary, $barangay, $household, $resident] = $this->fixture();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->mock(RbiTemplatePdfGenerator::class, function ($mock) use ($secretary) {
            $mock->shouldReceive('generateResidents')->times(3)->withArgs(fn ($records, $context) =>
                $context['barangay_secretary_name'] === $secretary->display_name)->andReturn('%PDF-test');
            $mock->shouldReceive('generateHouseholds')->times(3)->withArgs(fn ($records, $context) =>
                $context['officials']['barangay_secretary_name'] === $secretary->display_name
                && $context['officials']['punong_barangay_name'] === 'Manual Captain')->andReturn('%PDF-test');
        });
        $this->actingAs($admin);
        foreach (['resident_rbi', 'household_rbi'] as $type) {
            $this->get(route('admin.documents.export', ['document_type' => $type, 'barangay_id' => $barangay->id]))->assertOk();
        }
        foreach (['residents' => $resident, 'households' => $household] as $dataset => $record) {
            foreach (['pdf', 'print'] as $action) {
                $this->get(route('admin.'.$dataset.'.'.$action, $record))->assertOk();
            }
        }
        $this->assertSame([$admin->id], AuditLog::where('event_type', 'exported')->distinct()->pluck('user_id')->all());
    }

    private function assertLinkedInput(string $html, string $id, string $name): void
    {
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $input = $document->getElementById($id);
        $this->assertNotNull($input);
        $this->assertTrue($input->hasAttribute('disabled'));
        $this->assertSame($name, $input->getAttribute('value'));
        $this->assertTrue($input->hasAttribute('aria-describedby'));
    }

    private function assertFallback(Barangay $barangay): void
    {
        $this->assertSame('Manual Secretary', $barangay->officials()->where('role_key', 'barangay_secretary')->value('official_name'));
    }

    private function fixture(): array
    {
        $barangay = Barangay::factory()->create();
        $barangay->officials()->where('role_key', 'barangay_secretary')->update(['official_name' => 'Manual Secretary']);
        $barangay->officials()->where('role_key', 'punong_barangay')->update(['official_name' => 'Manual Captain']);
        $secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id,
            'first_name' => 'Account', 'last_name' => 'Secretary']);
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id]);
        $household = Household::create(['purok_id' => $purok->id, 'household_no' => '001', 'household_address' => 'Fixture', 'is_active' => true]);
        $resident = Resident::create(['household_id' => $household->id, 'first_name' => 'Resident', 'last_name' => 'Fixture',
            'birth_date' => '1990-01-01', 'birth_place' => 'Tubigon', 'sex' => 'Female', 'civil_status' => 'Single',
            'citizenship' => 'Filipino', 'relationship_to_head' => 'Head of Household', 'resident_status' => 'active', 'is_active' => true]);

        return [$secretary, $barangay, $household, $resident];
    }
}
