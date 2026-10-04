<?php

namespace Tests\Feature\Secretary;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\BarangayCertificate;
use App\Models\BarangayOfficial;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Support\RbiTemplatePdfGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarangayOfficialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_roster_displays_ten_positions_names_and_unassigned_slots_without_edit_fields(): void
    {
        [$secretary, $barangay] = $this->fixture();
        $response = $this->actingAs($secretary)->get(route('secretary.officials.index'))->assertOk()
            ->assertSee('Official Barangay Roster')->assertSee($secretary->display_name)->assertSee('System-linked')
            ->assertSee('Roster Captain')->assertSee('Not assigned')->assertSee('Edit Roster')
            ->assertDontSee('name="officials[', false);

        $this->assertCount(10, $response->viewData('officials'));
        foreach (BarangayOfficial::defaults() as $slot) {
            $response->assertSee($slot['official_title']);
        }
        $this->assertCount(10, $barangay->officials()->get());
    }

    public function test_edit_has_all_ten_current_values_and_reuses_put_contract_and_canonical_actions(): void
    {
        [$secretary] = $this->fixture();
        $response = $this->actingAs($secretary)->get(route('secretary.officials.edit'))->assertOk()
            ->assertSee('action="'.route('secretary.documents.officials.update').'"', false)
            ->assertSee('name="_method" value="PUT"', false)->assertSee('name="_token"', false)
            ->assertSee('value="'.$secretary->display_name.'"', false)->assertSee('value="Roster Captain"', false)
            ->assertSee('data-record-action="edit"', false)->assertSee('data-record-action="back"', false)
            ->assertDontSee('name="barangay_id"', false)->assertDontSee('data-confirm-skip', false);

        foreach (BarangayOfficial::defaults() as $slot) {
            $response->assertSee('name="officials['.$slot['role_key'].']"', false);
        }
    }

    public function test_complete_save_updates_same_records_trims_and_audits_without_clearing_unaffected_names(): void
    {
        [$secretary, $barangay] = $this->fixture();
        $ids = $barangay->officials()->pluck('id', 'role_key')->all();
        $names = $this->names($barangay);
        $names['punong_barangay'] = '  New Captain  ';
        $names['barangay_treasurer'] = '   ';

        $this->actingAs($secretary)->from(route('secretary.officials.edit'))
            ->put(route('secretary.documents.officials.update'), ['officials' => $names])
            ->assertRedirect(route('secretary.officials.edit'))
            ->assertSessionHas('success', 'Barangay officials updated for document attestation fields.');

        $this->assertSame($ids, $barangay->officials()->pluck('id', 'role_key')->all());
        $this->assertSame('New Captain', $this->names($barangay)['punong_barangay']);
        $this->assertSame('Roster Secretary', $this->names($barangay)['barangay_secretary']);
        $this->assertNull($this->names($barangay)['barangay_treasurer']);
        $this->assertSame(9, AuditLog::where('model_type', BarangayOfficial::class)
            ->where('user_id', $secretary->id)->where('event_type', 'updated')->count());
    }

    public function test_validation_retains_input_displays_field_error_and_leaves_names_unchanged(): void
    {
        [$secretary, $barangay] = $this->fixture();
        $names = $this->names($barangay);
        $names['punong_barangay'] = str_repeat('A', 151);
        $names['barangay_secretary'] = 'Submitted Secretary';
        $response = $this->actingAs($secretary)->from(route('secretary.officials.edit'))->followingRedirects()
            ->put(route('secretary.documents.officials.update'), ['officials' => $names])
            ->assertOk();
        $this->assertTrue($response->viewData('errors')->has('officials.punong_barangay'));
        $response
            ->assertSee('value="'.$names['punong_barangay'].'"', false)
            ->assertSee('value="'.$secretary->display_name.'"', false)->assertDontSee('value="Submitted Secretary"', false)
            ->assertSee('id="error_punong_barangay"', false)
            ->assertSee('must not be greater than 150 characters');
        $this->assertSame('Roster Captain', $this->names($barangay)['punong_barangay']);
    }

    public function test_client_barangay_selector_cannot_read_or_update_foreign_roster(): void
    {
        [$secretary, $barangay] = $this->fixture();
        $foreign = Barangay::factory()->create();
        $foreign->officials()->where('role_key', 'punong_barangay')->update(['official_name' => 'Foreign Captain']);
        $this->actingAs($secretary)->get(route('secretary.officials.index', ['barangay_id' => $foreign->id]))
            ->assertOk()->assertSee('Roster Captain')->assertDontSee('Foreign Captain');
        $this->put(route('secretary.documents.officials.update'), [
            'barangay_id' => $foreign->id,
            'officials' => array_replace($this->names($barangay), ['punong_barangay' => 'Scoped Captain']),
        ])->assertRedirect();
        $this->assertSame('Foreign Captain', $this->names($foreign)['punong_barangay']);
        $this->assertSame('Scoped Captain', $this->names($barangay)['punong_barangay']);
    }

    public function test_unassigned_or_deleted_assignment_fails_safely_and_other_roles_cannot_access(): void
    {
        [$secretary, $barangay] = $this->fixture();
        foreach ([null, $barangay->id] as $assignment) {
            if ($assignment !== null) {
                $barangay->delete();
            }
            $secretary->update(['assigned_barangay_id' => $assignment]);
            $this->actingAs($secretary);
            $this->get(route('secretary.officials.index'))->assertNotFound();
            $this->get(route('secretary.officials.edit'))->assertNotFound();
            $this->put(route('secretary.documents.officials.update'), ['officials' => []])->assertNotFound();
        }
        foreach (['admin', 'bhw', 'bns', 'phn', 'mho'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('secretary.officials.index'))->assertForbidden();
            $this->get(route('secretary.officials.edit'))->assertForbidden();
            $this->put(route('secretary.documents.officials.update'), ['officials' => []])->assertForbidden();
        }
    }

    public function test_documents_removes_only_secretary_panel_and_admin_save_still_works(): void
    {
        [$secretary, $barangay] = $this->fixture();
        $this->actingAs($secretary)->get(route('secretary.documents.index'))->assertOk()
            ->assertSee('RBI Document Generator')->assertDontSee('Attestation Settings')
            ->assertDontSee('name="officials[', false)->assertDontSee('xl:grid-cols-[1.1fr_0.9fr]', false);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.documents.index', ['barangay_id' => $barangay->id]))->assertOk()
            ->assertSee('Attestation Settings')->assertSee('name="officials[punong_barangay]"', false)
            ->assertSee('action="'.route('admin.documents.officials.update').'"', false)
            ->assertSee('xl:grid-cols-[1.1fr_0.9fr]', false);
        $this->put(route('admin.documents.officials.update'), [
            'barangay_id' => $barangay->id,
            'officials' => array_replace($this->names($barangay), ['punong_barangay' => 'Admin Captain']),
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('Admin Captain', $this->names($barangay)['punong_barangay']);
    }

    public function test_sidebar_roster_item_is_active_on_both_roster_pages(): void
    {
        [$secretary] = $this->fixture();
        foreach (['index', 'edit'] as $page) {
            $html = $this->actingAs($secretary)->get(route('secretary.officials.'.$page))->assertOk()->getContent();
            $document = new \DOMDocument;
            @$document->loadHTML($html);
            $xpath = new \DOMXPath($document);
            $link = $xpath->query('//a[@href="'.route('secretary.officials.index').'"][.//span[text()="Barangay Officials"]]')->item(0);
            $this->assertNotNull($link);
            $this->assertStringContainsString('ring-2 ring-white/20', $link->getAttribute('class'));
        }
    }

    public function test_all_six_rbi_consumers_use_linked_secretary_and_current_captain(): void
    {
        [$secretary, $barangay, $household, $resident] = $this->fixture();
        $this->mock(RbiTemplatePdfGenerator::class, function ($mock) use ($household, $resident, $secretary) {
            $mock->shouldReceive('generateResidents')->times(3)->withArgs(function ($records, $context) use ($resident, $secretary) {
                $this->assertSame($resident->id, collect($records)->first()->id);
                $this->assertSame($secretary->display_name, $context['barangay_secretary_name']);

                return true;
            })->andReturn('%PDF-test');
            $mock->shouldReceive('generateHouseholds')->times(3)->withArgs(function ($records, $context) use ($household, $secretary) {
                $this->assertSame($household->id, collect($records)->first()->id);
                $this->assertSame($secretary->display_name, $context['officials']['barangay_secretary_name']);
                $this->assertSame('Updated Captain', $context['officials']['punong_barangay_name']);

                return true;
            })->andReturn('%PDF-test');
        });
        $this->actingAs($secretary)->put(route('secretary.documents.officials.update'), [
            'officials' => array_replace($this->names($barangay), [
                'barangay_secretary' => 'Updated Secretary', 'punong_barangay' => 'Updated Captain',
            ]),
        ])->assertRedirect();
        foreach (['resident_rbi', 'household_rbi'] as $type) {
            $review = $this->get(route('secretary.documents.index', ['step' => 4, 'document_type' => $type, 'coverage' => 'barangay']))->assertOk();
            $this->get(route('secretary.documents.export', ['review' => $review->viewData('reviewToken')]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
        foreach (['residents' => $resident, 'households' => $household] as $dataset => $record) {
            foreach (['pdf', 'print'] as $action) {
                $this->get(route("secretary.$dataset.$action", $record))->assertOk()->assertHeader('Content-Type', 'application/pdf');
            }
        }
    }

    public function test_both_certificate_templates_keep_issuer_account_not_roster_signatory(): void
    {
        [$secretary, $barangay, , $resident] = $this->fixture();
        foreach ([BarangayCertificate::TYPE_CLEARANCE, BarangayCertificate::TYPE_INDIGENCY] as $type) {
            $certificate = BarangayCertificate::create([
                'barangay_id' => $barangay->id, 'certificate_type' => $type,
                'recipient_type' => 'resident', 'resident_id' => $resident->id,
                'certificate_no' => 'TEST-'.$type, 'issued_to_name' => 'Juana Dela Cruz',
                'purpose' => 'Test', 'issued_at' => now(), 'issued_by_user_id' => $secretary->id,
            ]);
            $html = view('secretary.certificates.pdf', ['certificate' => $certificate->load(['barangay', 'issuedBy'])])->render();
            $this->assertStringContainsString(e($secretary->name), $html);
            $this->assertStringNotContainsString('Roster Secretary', $html);
            $this->assertStringNotContainsString('Roster Captain', $html);
        }
    }

    private function names(Barangay $barangay): array
    {
        return $barangay->officials()->pluck('official_name', 'role_key')->all();
    }

    private function fixture(): array
    {
        $barangay = Barangay::factory()->create(['is_active' => true]);
        $barangay->officials()->where('role_key', 'barangay_secretary')->update(['official_name' => 'Roster Secretary']);
        $barangay->officials()->where('role_key', 'punong_barangay')->update(['official_name' => 'Roster Captain']);
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id, 'purok_number' => 1, 'is_active' => true]);
        $household = Household::create([
            'purok_id' => $purok->id, 'household_no' => '001', 'household_address' => 'Zone 1',
            'is_active' => true, 'is_social_aid_beneficiary' => false,
        ]);
        $resident = Resident::create([
            'household_id' => $household->id, 'first_name' => 'Juana', 'last_name' => 'Dela Cruz',
            'birth_date' => '1994-03-12', 'birth_place' => 'Tubigon, Bohol', 'sex' => 'Female',
            'civil_status' => 'Single', 'citizenship' => 'Filipino', 'relationship_to_head' => 'Daughter',
            'resident_status' => Resident::STATUS_ACTIVE, 'is_active' => true,
        ]);
        $secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id, 'assigned_purok_id' => null]);

        return [$secretary, $barangay, $household, $resident];
    }
}
