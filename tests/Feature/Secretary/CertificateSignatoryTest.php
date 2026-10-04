<?php

namespace Tests\Feature\Secretary;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\BarangayCertificate;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Support\BarangayOfficialsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CertificateSignatoryTest extends TestCase
{
    use RefreshDatabase;

    public static function certificateTypes(): array
    {
        return ['clearance' => [BarangayCertificate::TYPE_CLEARANCE, 'BCL'],
            'indigency' => [BarangayCertificate::TYPE_INDIGENCY, 'COI']];
    }

    #[DataProvider('certificateTypes')]
    public function test_issuance_snapshots_canonical_name_preserves_actor_and_numbering_and_renders_pdf(string $type, string $prefix): void
    {
        $this->freezeTime();
        [$secretary, $barangay, $resident] = $this->fixture();
        $certificate = $this->issue($secretary, $resident, $type, [
            'signatory_name_at_issuance' => 'Forged signatory', 'issued_by_user_id' => 999999,
        ]);
        $this->assertSame($secretary->display_name, $certificate->signatory_name_at_issuance);
        $this->assertSame($secretary->id, $certificate->issued_by_user_id);
        $this->assertSame(sprintf('%s-B%d-%s-0001', $prefix, $barangay->id, now()->year), $certificate->certificate_no);
        $this->assertDatabaseHas('audit_logs', ['event_type' => 'created', 'model_type' => BarangayCertificate::class,
            'model_id' => $certificate->id, 'user_id' => $secretary->id]);
        $this->assertPrintedName($certificate, $secretary->display_name, 'Manual Secretary');
        $response = $this->get(route('secretary.certificates.pdf', $certificate))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $second = $this->issue($secretary, $resident, $type);
        $this->assertSame(sprintf('%s-B%d-%s-0002', $prefix, $barangay->id, now()->year), $second->certificate_no);
    }

    public function test_final_issuance_consumes_resolver_without_substituting_actor_name(): void
    {
        [$actor, $barangay, $resident] = $this->fixture();
        $this->mock(BarangayOfficialsRegistry::class, function ($mock) use ($barangay) {
            $mock->shouldReceive('resolvedSecretaryName')->twice()
                ->withArgs(fn (Barangay $scope) => $scope->id === $barangay->id)
                ->andReturn('Resolved Officeholder');
        });
        $certificate = $this->issue($actor, $resident);
        $this->assertSame('Resolved Officeholder', $certificate->signatory_name_at_issuance);
        $this->assertSame($actor->id, $certificate->issued_by_user_id);
        $this->assertDatabaseHas('audit_logs', ['model_type' => BarangayCertificate::class,
            'model_id' => $certificate->id, 'user_id' => $actor->id]);
        $this->assertPrintedName($certificate, 'Resolved Officeholder', $actor->display_name);
    }

    public function test_account_rename_does_not_change_historical_signatory(): void
    {
        [$secretary, , $resident] = $this->fixture();
        $certificate = $this->issue($secretary, $resident);
        $originalName = $secretary->display_name;
        $secretary->update(['last_name' => 'Renamed Officeholder']);
        $this->assertPrintedName($certificate, $originalName, $secretary->fresh()->display_name);
        $this->assertSame($originalName, $certificate->fresh()->signatory_name_at_issuance);
        $this->actingAs($secretary->fresh())->get(route('secretary.certificates.pdf', $certificate))->assertOk();
    }

    public static function replacements(): array
    {
        return ['deactivated' => ['deactivate'], 'soft-deleted' => ['delete'], 'reassigned' => ['reassign']];
    }

    #[DataProvider('replacements')]
    public function test_replacement_preserves_old_snapshot_and_new_issuance_uses_new_officeholder(string $transition): void
    {
        [$old, $barangay, $resident] = $this->fixture();
        $certificate = $this->issue($old, $resident);
        $oldName = $old->display_name;
        match ($transition) {
            'delete' => $old->delete(),
            'reassign' => $old->update(['assigned_barangay_id' => Barangay::factory()->create()->id]),
            default => $old->update(['is_active' => false]),
        };
        $new = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id,
            'first_name' => 'Ana', 'last_name' => 'Replacement']);
        $this->assertPrintedName($certificate, $oldName, $new->display_name);
        $this->assertSame($oldName, $certificate->fresh()->signatory_name_at_issuance);
        $this->assertSame($old->id, $certificate->fresh()->issued_by_user_id);
        $this->actingAs($new)->get(route('secretary.certificates.pdf', $certificate))->assertOk();
        $newCertificate = $this->issue($new, $resident);
        $this->assertSame($new->display_name, $newCertificate->signatory_name_at_issuance);
        $this->assertPrintedName($newCertificate, $new->display_name, $oldName);
    }

    public function test_manual_fallback_is_snapshotted_when_no_account_qualifies(): void
    {
        [$actor, $barangay, $resident] = $this->fixture();
        // Existing Secretary access does not require an active barangay; resolution does.
        $barangay->update(['is_active' => false]);
        $this->assertSame('Manual Secretary', app(BarangayOfficialsRegistry::class)->resolvedSecretaryName($barangay));
        $certificate = $this->issue($actor, $resident);
        $this->assertSame('Manual Secretary', $certificate->signatory_name_at_issuance);
        $this->assertSame($actor->id, $certificate->issued_by_user_id);
        $barangay->officials()->where('role_key', 'barangay_secretary')->update(['official_name' => 'Later Manual Secretary']);
        $this->assertPrintedName($certificate, 'Manual Secretary', 'Later Manual Secretary');
    }

    public static function unresolvedNames(): array
    {
        return ['null' => [null], 'empty' => [''], 'whitespace' => ['   ']];
    }

    #[DataProvider('unresolvedNames')]
    public function test_unresolved_signatory_blocks_creation_without_certificate_or_audit(?string $name): void
    {
        [$actor, , $resident] = $this->fixture();
        $this->mock(BarangayOfficialsRegistry::class, function ($mock) use ($name) {
            $mock->shouldReceive('resolvedSecretaryName')->once()->andReturn($name);
        });
        $this->actingAs($actor)->post(route('secretary.certificates.store'), $this->payload($resident))
            ->assertSessionHasErrors('signatory_name_at_issuance')->assertSessionHas('error');
        $this->assertDatabaseCount('barangay_certificates', 0);
        $this->assertSame(0, AuditLog::where('model_type', BarangayCertificate::class)->count());
    }

    public function test_real_unresolved_manual_fallback_blocks_issuance_without_actor_fallback(): void
    {
        [$actor, $barangay, $resident] = $this->fixture();
        $barangay->update(['is_active' => false]);
        $barangay->officials()->where('role_key', 'barangay_secretary')->update(['official_name' => null]);
        $this->actingAs($actor)->from(route('secretary.certificates.create'))
            ->post(route('secretary.certificates.store'), $this->payload($resident))
            ->assertSessionHasErrors('signatory_name_at_issuance')->assertRedirect(route('secretary.certificates.create'));
        $this->assertDatabaseCount('barangay_certificates', 0);
        $this->assertSame(0, AuditLog::where('model_type', BarangayCertificate::class)->count());
        $this->get(route('secretary.certificates.create'))->assertOk()
            ->assertSee('An official Barangay Secretary name is required before issuing a certificate.');
    }

    public function test_legacy_null_snapshot_keeps_live_issuer_fallback_without_backfill(): void
    {
        [$issuer, $barangay, $resident] = $this->fixture();
        $legacy = BarangayCertificate::create($this->payload($resident) + [
            'barangay_id' => $barangay->id, 'certificate_no' => 'LEGACY-001',
            'issued_to_name' => 'Test Recipient', 'issued_by_user_id' => $issuer->id,
        ])->fresh();
        $this->assertNull($legacy->signatory_name_at_issuance);
        $issuer->update(['last_name' => 'Renamed Legacy Issuer', 'is_active' => false]);
        $current = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id,
            'first_name' => 'Current', 'last_name' => 'Officeholder']);
        $before = $legacy->getAttributes();
        $this->assertPrintedName($legacy, $issuer->fresh()->name, $current->display_name);
        $this->actingAs($current)->get(route('secretary.certificates.pdf', $legacy))->assertOk();
        $this->assertNull($legacy->fresh()->signatory_name_at_issuance);
        $this->assertSame($before, $legacy->fresh()->getAttributes());
    }

    public function test_issuance_snapshot_cannot_be_overwritten_or_erased_on_model_update(): void
    {
        [$secretary, , $resident] = $this->fixture();
        $certificate = $this->issue($secretary, $resident);
        foreach (['Replacement Name', null] as $replacement) {
            try {
                $certificate->update(['signatory_name_at_issuance' => $replacement]);
                $this->fail('Historical signatory changes must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('signatory_name_at_issuance', $exception->errors());
            }
            $certificate->refresh();
            $this->assertSame($secretary->display_name, $certificate->signatory_name_at_issuance);
        }
    }

    private function issue(User $actor, Resident $resident, string $type = BarangayCertificate::TYPE_CLEARANCE, array $extra = []): BarangayCertificate
    {
        $token = $this->actingAs($actor)->get(route('secretary.certificates.create'))->assertOk()->viewData('reviewToken');
        $response = $this->post(route('secretary.certificates.store'), $extra + $this->payload($resident, $type) + ['review_token' => $token]);
        $response->assertSessionHasNoErrors();
        $certificate = BarangayCertificate::latest('id')->firstOrFail();
        $response->assertRedirect(route('secretary.certificates.show', $certificate));

        return $certificate;
    }

    private function payload(Resident $resident, string $type = BarangayCertificate::TYPE_CLEARANCE): array
    {
        return ['certificate_type' => $type, 'recipient_type' => 'resident', 'resident_id' => $resident->id,
            'purpose' => 'Test application', 'issued_at' => now()->timezone('Asia/Manila')->format('Y-m-d\TH:i:s')];
    }

    private function assertPrintedName(BarangayCertificate $certificate, string $expected, string $absent): void
    {
        $html = view('secretary.certificates.pdf', ['certificate' => $certificate->fresh()->load(['barangay', 'issuedBy'])])->render();
        $this->assertStringContainsString('<div><strong>'.e($expected).'</strong></div>', $html);
        $this->assertStringNotContainsString('<div><strong>'.e($absent).'</strong></div>', $html);
    }

    private function fixture(): array
    {
        $barangay = Barangay::factory()->create();
        $barangay->officials()->where('role_key', 'barangay_secretary')->update(['official_name' => 'Manual Secretary']);
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id]);
        $household = Household::create(['purok_id' => $purok->id, 'household_no' => '001',
            'household_address' => 'Test Address', 'is_active' => true]);
        $resident = Resident::create(['household_id' => $household->id, 'first_name' => 'Test', 'last_name' => 'Recipient',
            'birth_date' => '1990-01-01', 'birth_place' => 'Tubigon', 'sex' => 'Female', 'civil_status' => 'Single',
            'citizenship' => 'Filipino', 'relationship_to_head' => 'Head of Household', 'resident_status' => 'active', 'is_active' => true]);
        $secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id,
            'first_name' => 'Maria', 'middle_name' => 'Elena', 'last_name' => 'Original', 'suffix' => 'II']);

        return [$secretary, $barangay, $resident];
    }
}
