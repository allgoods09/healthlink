<?php

namespace Tests\Feature\Secretary;

use App\Models\Barangay;
use App\Models\BarangayCertificate;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Support\BarangayOfficialsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CertificateWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_wizard_has_four_steps_scoped_choices_canonical_signatories_and_manila_default(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 4)->setTime(16, 49));
        [$actor, $resident, $household] = $this->fixture();
        [, $foreignResident, $foreignHousehold] = $this->fixture();
        $response = $this->actingAs($actor)->get(route('secretary.certificates.create'))->assertOk();
        if ($path = getenv('CERTIFICATE_BROWSER_HTML')) {
            file_put_contents($path, $response->getContent());
        }
        $response->assertSee('1. Certificate')->assertSee('2. Recipient')->assertSee('3. Details')->assertSee('4. Review &amp; Issue', false)
            ->assertSee('Barangay Clearance')->assertSee('Certificate of Indigency')->assertSee('Resident')->assertSee('Household')
            ->assertSee('value="2026-10-05T00:49"', false)->assertSee('Official Barangay Secretary')->assertSee('Issued By');
        $this->assertSame([$resident->id], $response->viewData('residents')->modelKeys());
        $this->assertSame([$household->id], $response->viewData('households')->modelKeys());
        $this->assertNotContains($foreignResident->id, $response->viewData('residents')->modelKeys());
        $this->assertNotContains($foreignHousehold->id, $response->viewData('households')->modelKeys());
        $this->assertSame($actor->display_name, $response->viewData('officialSecretary'));
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertDatabaseCount('barangay_certificates', 0);
    }

    public static function residentStates(): array
    {
        return ['inactive' => ['inactive'], 'deceased' => ['deceased'], 'relocated' => ['relocated'],
            'deleted' => ['deleted'], 'foreign' => ['foreign']];
    }

    #[DataProvider('residentStates')]
    public function test_unavailable_resident_is_rejected_on_final_submission(string $state): void
    {
        [$actor, $resident] = $this->fixture();
        $payload = $this->payload($actor, $resident);
        if ($state === 'foreign') [, $resident] = $this->fixture();
        elseif ($state === 'deleted') $resident->delete();
        elseif ($state === 'inactive') $resident->update(['is_active' => false]);
        else $resident->update(['resident_status' => $state]);
        $payload['resident_id'] = $resident->id;
        $this->post(route('secretary.certificates.store'), $payload)->assertSessionHasErrors('resident_id');
        $this->assertDatabaseCount('barangay_certificates', 0);
    }

    public static function householdStates(): array
    {
        return ['inactive' => ['inactive'], 'deleted' => ['deleted'], 'foreign' => ['foreign']];
    }

    #[DataProvider('householdStates')]
    public function test_unavailable_household_is_rejected_on_final_submission(string $state): void
    {
        [$actor, $resident, $household] = $this->fixture();
        $payload = $this->payload($actor, $resident);
        if ($state === 'foreign') [, , $household] = $this->fixture();
        elseif ($state === 'deleted') $household->delete();
        else $household->update(['is_active' => false]);
        $payload['recipient_type'] = 'household';
        $payload['household_id'] = $household->id;
        $this->post(route('secretary.certificates.store'), $payload)->assertSessionHasErrors('household_id');
        $this->assertDatabaseCount('barangay_certificates', 0);
    }

    public static function recipientTypes(): array
    {
        return ['resident' => ['resident'], 'household' => ['household']];
    }

    #[DataProvider('recipientTypes')]
    public function test_inactive_branch_cannot_smuggle_an_authoritative_id(string $type): void
    {
        [$actor, $resident, $household] = $this->fixture();
        $payload = $this->payload($actor, $resident) + ['household_id' => $household->id];
        $payload['recipient_type'] = $type;
        $payload[$type === 'resident' ? 'household_id' : 'resident_id'] = 999999;
        $this->post(route('secretary.certificates.store'), $payload)->assertSessionHasNoErrors();
        $certificate = BarangayCertificate::firstOrFail();
        $this->assertSame($type === 'resident' ? $resident->id : null, $certificate->resident_id);
        $this->assertSame($type === 'household' ? $household->id : null, $certificate->household_id);
    }

    public function test_purpose_limit_and_old_input_reopen_details_without_creation(): void
    {
        [$actor, $resident] = $this->fixture();
        $payload = $this->payload($actor, $resident);
        $payload['purpose'] = str_repeat('P', 256);
        $this->from(route('secretary.certificates.create'))->post(route('secretary.certificates.store'), $payload)
            ->assertSessionHasErrors('purpose')->assertSessionHasInput('purpose', $payload['purpose']);
        $this->assertSame(['purpose'], array_keys(session('errors')->getBag('default')->messages()));
        $this->withCookie(config('session.cookie'), session()->getId());
        $page = $this->get(route('secretary.certificates.create'))->assertOk()->assertSee('2026-10-04T12:49');
        preg_match('/data-certificate-start-step="(\d)"/', $page->getContent(), $match);
        $this->assertSame('3', $match[1] ?? null);
        $this->assertDatabaseCount('barangay_certificates', 0);
        $payload['purpose'] = str_repeat('P', 255);
        $this->post(route('secretary.certificates.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame($payload['purpose'], BarangayCertificate::firstOrFail()->purpose);
    }

    public function test_override_requires_opt_in_and_manila_time_is_stored_as_utc(): void
    {
        [$actor, $resident] = $this->fixture();
        $payload = $this->payload($actor, $resident) + ['use_printed_name' => '0', 'issued_to_name' => 'Stale Override'];
        $this->post(route('secretary.certificates.store'), $payload)->assertSessionHasNoErrors();
        $certificate = BarangayCertificate::firstOrFail();
        $this->assertSame($resident->formal_name, $certificate->issued_to_name);
        $this->assertSame('2026-10-04 04:49:00', $certificate->issued_at->format('Y-m-d H:i:s'));
        $this->assertSame($actor->id, $certificate->issued_by_user_id);
        $this->assertSame($actor->display_name, $certificate->signatory_name_at_issuance);
        $payload['use_printed_name'] = '1';
        $payload['issued_to_name'] = 'Confirmed Printed Name';
        $this->post(route('secretary.certificates.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame('Confirmed Printed Name', BarangayCertificate::latest('id')->firstOrFail()->issued_to_name);
    }

    public function test_changed_officeholder_and_tampered_review_are_rejected_then_reopened_at_review(): void
    {
        [$actor, $resident] = $this->fixture();
        $payload = $this->payload($actor, $resident);
        $actor->update(['last_name' => 'Changed Secretary']);
        $this->actingAs($actor->fresh())->from(route('secretary.certificates.create'))
            ->post(route('secretary.certificates.store'), $payload)->assertSessionHasErrors('review_token');
        $this->assertSame(['review_token'], array_keys(session('errors')->getBag('default')->messages()));
        $this->withCookie(config('session.cookie'), session()->getId());
        $page = $this->get(route('secretary.certificates.create'))->assertOk()->assertSee('Changed Secretary');
        preg_match('/data-certificate-start-step="(\d)"/', $page->getContent(), $match);
        $this->assertSame('4', $match[1] ?? null);
        $this->assertDatabaseCount('barangay_certificates', 0);
        $payload['review_token'] = 'Forged Browser Name';
        $this->post(route('secretary.certificates.store'), $payload)->assertSessionHasErrors('review_token');
        $payload = $this->payload($actor->fresh(), $resident);
        $this->post(route('secretary.certificates.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame($actor->fresh()->display_name, BarangayCertificate::firstOrFail()->signatory_name_at_issuance);
    }

    public function test_recipient_is_checked_again_after_request_validation_even_with_override(): void
    {
        [$actor, $resident] = $this->fixture();
        $payload = $this->payload($actor, $resident) + ['use_printed_name' => '1', 'issued_to_name' => 'Override'];
        $this->mock(BarangayOfficialsRegistry::class, function ($mock) use ($resident, $actor) {
            $mock->shouldReceive('resolvedSecretaryName')->once()->andReturnUsing(function () use ($resident, $actor) {
                $resident->update(['is_active' => false]);
                return $actor->display_name;
            });
        });
        $this->post(route('secretary.certificates.store'), $payload)->assertSessionHasErrors('resident_id');
        $this->assertDatabaseCount('barangay_certificates', 0);
    }

    public function test_log_and_details_use_canonical_actions_and_keep_export(): void
    {
        [$actor, $resident] = $this->fixture();
        $this->post(route('secretary.certificates.store'), $this->payload($actor, $resident))->assertSessionHasNoErrors();
        $certificate = BarangayCertificate::firstOrFail();
        $this->get(route('secretary.certificates.index'))->assertOk()->assertSee('data-record-action="add"', false)
            ->assertSee('data-record-action="view"', false)->assertSee('data-record-action="document"', false)
            ->assertSee(route('secretary.certificates.export', 'csv'));
        $this->get(route('secretary.certificates.show', $certificate))->assertOk()->assertSee('data-record-action="document"', false)
            ->assertSee('data-record-action="back"', false)->assertSee('data-record-action="add"', false);
        $this->get(route('secretary.certificates.pdf', $certificate))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    private function payload(User $actor, Resident $resident): array
    {
        $token = $this->actingAs($actor)->get(route('secretary.certificates.create'))->assertOk()->viewData('reviewToken');
        return ['certificate_type' => 'barangay_clearance', 'recipient_type' => 'resident', 'resident_id' => $resident->id,
            'purpose' => 'Employment', 'issued_at' => '2026-10-04T12:49', 'review_token' => $token];
    }

    private function fixture(): array
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id]);
        $household = Household::create(['purok_id' => $purok->id, 'household_no' => '056', 'household_address' => 'Fixture', 'is_active' => true]);
        $resident = Resident::create(['household_id' => $household->id, 'first_name' => 'Gavin', 'last_name' => 'Fixture',
            'birth_date' => '1990-01-01', 'birth_place' => 'Tubigon', 'sex' => 'Male', 'civil_status' => 'Single',
            'citizenship' => 'Filipino', 'relationship_to_head' => 'Head of Household', 'is_active' => true, 'resident_status' => 'active']);
        $household->update(['head_resident_id' => $resident->id]);
        $actor = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id]);
        return [$actor, $resident, $household];
    }
}
