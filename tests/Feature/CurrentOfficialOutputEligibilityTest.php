<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayCertificate;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Support\BarangayOfficialsRegistry;
use App\Support\RbiTemplatePdfGenerator;
use App\Support\SecretaryRbiSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CurrentOfficialOutputEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $secretary;

    private Household $home;

    private Household $vacant;

    private array $people;

    protected function setUp(): void
    {
        parent::setUp();
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id]);
        $this->secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id]);
        $this->home = Household::create(['purok_id' => $purok->id, 'household_no' => '001',
            'household_address' => 'Synthetic street', 'is_active' => true]);
        foreach (['active', 'unavailable', 'deceased', 'moved_out', 'relocated', 'deleted'] as $state) {
            $this->people[$state] = $this->person($this->home, ucfirst($state).'Fixture', $state);
        }
        // Invalid historical heads must not be inserted into current Form A output.
        $this->home->update(['head_resident_id' => $this->people['deceased']->id]);
        $this->vacant = Household::create(['purok_id' => $purok->id, 'household_no' => '002',
            'household_address' => 'Historical street', 'is_active' => true]);
        $this->person($this->vacant, 'HistoricalOnly', 'relocated');
        $this->person($this->vacant, 'ArchivedOnly', 'deleted');
        $foreign = Household::create(['purok_id' => Purok::factory()->create(['barangay_id' => Barangay::factory()->create()->id])->id,
            'household_no' => '001', 'household_address' => 'Foreign street', 'is_active' => true]);
        $this->person($foreign, 'ForeignFixture', 'active');
        $this->actingAs($this->secretary);
    }

    private function person(Household $home, string $name, string $state): Resident
    {
        $person = Resident::create(['household_id' => $home->id, 'first_name' => $name, 'last_name' => 'Pilot',
            'birth_date' => '1995-01-01', 'birth_place' => 'Tubigon', 'sex' => 'Female', 'civil_status' => 'Single',
            'citizenship' => 'Filipino', 'relationship_to_head' => 'Child', 'is_active' => $state !== 'unavailable',
            'resident_status' => in_array($state, ['unavailable', 'deleted'], true) ? 'active' : $state]);
        if ($state === 'deleted') {
            $person->delete();
        }

        return $person;
    }

    public static function residentStates(): array
    {
        return [['active', true], ['unavailable', true], ['deceased', false], ['moved_out', false],
            ['relocated', false], ['deleted', false]];
    }

    public static function directStates(): array
    {
        $cases = [];
        foreach (['secretary', 'admin'] as $role) {
            foreach (self::residentStates() as [$state, $eligible]) {
                $cases[$role.' '.$state] = [$role, $state, $eligible];
            }
        }

        return $cases;
    }

    public static function roles(): array
    {
        return [['secretary'], ['admin']];
    }

    #[DataProvider('directStates')]
    public function test_direct_form_b_pdf_and_print_enforce_current_population(string $role, string $state, bool $eligible): void
    {
        $this->actingAs($role === 'secretary' ? $this->secretary : User::factory()->create(['role' => 'admin']));
        $resident = $this->people[$state];
        foreach (['pdf', 'print'] as $output) {
            $response = $this->get(route($role.'.residents.'.$output, $resident));
            if ($eligible) {
                $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
                $this->assertStringContainsString($resident->first_name, $this->pdfText($response->getContent()));
            } elseif ($state === 'deleted') {
                $response->assertNotFound();
            } else {
                $response->assertSessionHasErrors('resident')->assertRedirect();
            }
        }
    }

    #[DataProvider('roles')]
    public function test_form_a_pdf_and_print_include_only_current_members_and_keep_history(string $role): void
    {
        $this->actingAs($role === 'secretary' ? $this->secretary : User::factory()->create(['role' => 'admin']));
        $before = Resident::withTrashed()->orderBy('id')->get()->toJson();
        foreach (['pdf', 'print'] as $output) {
            $response = $this->get(route($role.'.households.'.$output, $this->home))->assertOk();
            $text = $this->pdfText($response->getContent());
            foreach ($this->people as $state => $person) {
                if (in_array($state, ['active', 'unavailable'], true)) {
                    $this->assertStringContainsString($person->first_name, $text);
                } else {
                    $this->assertStringNotContainsString($person->first_name, $text);
                }
            }
            $this->assertMatchesRegularExpression('/NO\. OF HOUSEHOLD MEMBERS:\s*2/', $text);
        }
        $this->assertSame($before, Resident::withTrashed()->orderBy('id')->get()->toJson());
        $this->assertSame($this->people['deceased']->id, $this->home->fresh()->head_resident_id);
    }

    #[DataProvider('roles')]
    public function test_vacant_form_a_is_rejected_before_rendering_without_changing_household(string $role): void
    {
        $this->actingAs($role === 'secretary' ? $this->secretary : User::factory()->create(['role' => 'admin']));
        $before = $this->vacant->fresh()->getAttributes();
        $this->mock(RbiTemplatePdfGenerator::class)->shouldNotReceive('generateHouseholds');
        foreach (['pdf', 'print'] as $output) {
            $this->get(route($role.'.households.'.$output, $this->vacant))->assertRedirect()
                ->assertSessionHasErrors(['household' => 'This household currently has no registered members.']);
        }
        $this->assertSame($before, $this->vacant->fresh()->getAttributes());
    }

    public function test_generator_itself_rejects_vacant_form_a_and_noncurrent_form_b(): void
    {
        foreach ([$this->vacant, $this->people['deceased'], $this->people['deleted']] as $record) {
            try {
                if ($record instanceof Household) {
                    app(RbiTemplatePdfGenerator::class)->generateHouseholds([$record]);
                } else {
                    app(RbiTemplatePdfGenerator::class)->generateResidents([$record]);
                }
                $this->fail('Ineligible RBI data must not render.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($record instanceof Household ? 'household' : 'resident', $exception->errors());
            }
        }
    }

    public static function selections(): array
    {
        $cases = [];
        foreach (['household_rbi', 'resident_rbi'] as $type) {
            foreach (['barangay', 'puroks', 'households'] as $coverage) {
                $cases[$type.' '.$coverage] = [$type, $coverage];
            }
        }

        return $cases;
    }

    #[DataProvider('selections')]
    public function test_canonical_selection_review_locked_output_and_admin_have_identical_eligibility(string $type, string $coverage): void
    {
        $selector = app(SecretaryRbiSelection::class);
        $input = ['document_type' => $type, 'coverage' => $coverage, 'purok_ids' => [$this->home->purok_id],
            'household_ids' => [$this->home->id], 'record_status' => 'all', 'resident_status' => 'deceased'];
        $selection = $selector->normalize($input, $this->secretary->assigned_barangay_id);
        $expected = $type === 'household_rbi' ? [$this->home->id]
            : Resident::currentPopulation()->where('household_id', $this->home->id)->orderBy('last_name')->orderBy('first_name')->pluck('id')->all();
        $this->assertSame($expected, $selector->query($selection, $this->secretary->assigned_barangay_id)->pluck('id')->all());
        $review = $this->get(route('secretary.documents.index', $input + ['step' => 4]))->assertOk();
        $this->assertSame(count($expected), $review->viewData('previewCount'));
        $token = $review->viewData('reviewToken');
        $locked = json_decode(Crypt::decryptString($token), true);
        $this->assertSame($selection, $locked['selection']);
        $method = $type === 'household_rbi' ? 'generateHouseholds' : 'generateResidents';
        $this->mock(RbiTemplatePdfGenerator::class)->shouldReceive($method)->twice()->withArgs(function ($records) use ($expected, $type) {
            $this->assertSame($expected, $records->pluck('id')->all());
            if ($type === 'household_rbi') {
                $this->assertEqualsCanonicalizing([$this->people['active']->id, $this->people['unavailable']->id], $records->first()->currentMembers->modelKeys());
            }

            return true;
        })->andReturn('%PDF-test');
        $this->get(route('secretary.documents.export', ['review' => $token]))->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $adminInput = ['barangay_id' => $this->secretary->assigned_barangay_id, 'document_type' => $type,
            'record_status' => 'all', 'resident_status' => 'deceased'];
        if ($coverage === 'puroks') {
            $adminInput['purok_id'] = $this->home->purok_id;
        } elseif ($coverage === 'households') {
            $adminInput['household_id'] = $this->home->id;
        }
        $adminReview = $this->get(route('admin.documents.index', $adminInput))->assertOk();
        $this->assertSame(count($expected), $adminReview->viewData('previewCount'));
        $this->get(route('admin.documents.export', $adminInput))->assertOk();
    }

    public function test_obsolete_resident_filters_cannot_broaden_or_exclude_current_output(): void
    {
        $selector = app(SecretaryRbiSelection::class);
        foreach (['active', 'inactive', 'all'] as $availability) {
            foreach (['deceased', 'moved_out', 'relocated'] as $status) {
                $selection = $selector->normalize(['document_type' => 'resident_rbi', 'coverage' => 'barangay',
                    'record_status' => $availability, 'resident_status' => $status], $this->secretary->assigned_barangay_id);
                $this->assertArrayNotHasKey('record_status', $selection);
                $this->assertArrayNotHasKey('resident_status', $selection);
                $this->assertSame(2, $selector->query($selection, $this->secretary->assigned_barangay_id)->count());
            }
        }
        $this->get(route('secretary.documents.index', ['step' => 3, 'document_type' => 'resident_rbi', 'coverage' => 'barangay']))
            ->assertOk()->assertDontSee('name="record_status"', false)->assertDontSee('name="resident_status"', false);
        $this->get(route('secretary.documents.index', ['step' => 2, 'document_type' => 'household_rbi']))->assertOk()
            ->assertViewHas('households', fn ($homes) => $homes->modelKeys() === [$this->home->id]);
    }

    public function test_final_documents_generation_excludes_residents_whose_lifecycle_changed_after_review(): void
    {
        $review = $this->get(route('secretary.documents.index', ['step' => 4, 'document_type' => 'resident_rbi', 'coverage' => 'barangay']))->assertOk();
        $this->people['active']->update(['resident_status' => 'moved_out']);
        $this->mock(RbiTemplatePdfGenerator::class)->shouldReceive('generateResidents')->once()
            ->withArgs(fn ($records) => $records->modelKeys() === [$this->people['unavailable']->id])->andReturn('%PDF-test');
        $this->get(route('secretary.documents.export', ['review' => $review->viewData('reviewToken')]))->assertOk();
    }

    #[DataProvider('roles')]
    public function test_real_documents_pdf_output_matches_current_review_for_both_forms(string $role): void
    {
        $this->actingAs($role === 'secretary' ? $this->secretary : User::factory()->create(['role' => 'admin']));
        foreach (['household_rbi', 'resident_rbi'] as $type) {
            if ($role === 'secretary') {
                $review = $this->get(route('secretary.documents.index', ['step' => 4, 'document_type' => $type, 'coverage' => 'barangay']))->assertOk();
                $params = ['review' => $review->viewData('reviewToken')];
            } else {
                $params = ['barangay_id' => $this->secretary->assigned_barangay_id, 'document_type' => $type];
                $review = $this->get(route('admin.documents.index', $params))->assertOk();
            }
            $this->assertSame($type === 'household_rbi' ? 1 : 2, $review->viewData('previewCount'));
            $response = $this->get(route($role.'.documents.export', $params))->assertOk();
            $text = $this->pdfText($response->getContent());
            $this->assertStringContainsString($this->people['active']->first_name, $text);
            $this->assertStringContainsString($this->people['unavailable']->first_name, $text);
            foreach (['deceased', 'moved_out', 'relocated', 'deleted'] as $state) {
                $this->assertStringNotContainsString($this->people[$state]->first_name, $text);
            }
            $this->assertStringNotContainsString('ForeignFixture', $text);
            $this->assertStringNotContainsString('HistoricalOnly', $text);
            $pages = array_filter(explode("\f", $text), fn ($page) => trim($page) !== '');
            $this->assertCount($review->viewData('previewCount'), $pages);
        }
    }

    public function test_eligibility_does_not_replace_secretary_authorization(): void
    {
        $foreign = Resident::where('first_name', 'ForeignFixture')->firstOrFail();
        foreach (['pdf', 'print'] as $output) {
            $this->get(route('secretary.residents.'.$output, $foreign))->assertForbidden();
            $this->get(route('secretary.households.'.$output, $foreign->household))->assertForbidden();
        }
        $this->get(route('secretary.documents.index', ['step' => 4, 'document_type' => 'resident_rbi',
            'coverage' => 'households', 'household_ids' => [$foreign->household_id]]))->assertRedirect()->assertSessionHasErrors('household_ids');
        $form = $this->get(route('secretary.certificates.create'))->assertOk();
        $this->post(route('secretary.certificates.store'), $this->certificatePayload($form->viewData('reviewToken')) + ['resident_id' => $foreign->id])
            ->assertSessionHasErrors('resident_id');
        $this->assertDatabaseCount('barangay_certificates', 0);
    }

    public function test_form_a_selection_is_revalidated_if_household_becomes_vacant_after_review(): void
    {
        $review = $this->get(route('secretary.documents.index', ['step' => 4, 'document_type' => 'household_rbi',
            'coverage' => 'households', 'household_ids' => [$this->home->id]]))->assertOk();
        $this->people['active']->update(['resident_status' => 'deceased']);
        $this->people['unavailable']->update(['resident_status' => 'moved_out']);
        $this->mock(RbiTemplatePdfGenerator::class)->shouldNotReceive('generateHouseholds');
        $this->get(route('secretary.documents.export', ['review' => $review->viewData('reviewToken')]))->assertRedirect()->assertSessionHas('error');
        $this->get(route('secretary.documents.index', ['step' => 4, 'document_type' => 'household_rbi', 'coverage' => 'barangay']))
            ->assertOk()->assertViewHas('previewCount', 0)->assertViewHas('reviewToken', null);
    }

    #[DataProvider('residentStates')]
    public function test_certificate_choices_validation_and_issuance_match_current_population(string $state, bool $eligible): void
    {
        $recipient = $this->people[$state];
        $form = $this->get(route('secretary.certificates.create'))->assertOk();
        $this->assertEqualsCanonicalizing([$this->people['active']->id, $this->people['unavailable']->id], $form->viewData('residents')->modelKeys());
        $this->assertSame([$this->home->id], $form->viewData('households')->modelKeys());
        $payload = $this->certificatePayload($form->viewData('reviewToken')) + ['resident_id' => $recipient->id];
        $response = $this->post(route('secretary.certificates.store'), $payload);
        if ($eligible) {
            $response->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseHas('barangay_certificates', ['resident_id' => $recipient->id, 'signatory_name_at_issuance' => $this->secretary->display_name]);
        } else {
            $response->assertSessionHasErrors('resident_id');
            $this->assertDatabaseCount('barangay_certificates', 0);
        }
    }

    public function test_new_household_certificates_require_current_members_in_validation_and_final_resolution(): void
    {
        $form = $this->get(route('secretary.certificates.create'))->assertOk();
        $payload = array_replace($this->certificatePayload($form->viewData('reviewToken')), ['recipient_type' => 'household', 'household_id' => $this->vacant->id]);
        $this->post(route('secretary.certificates.store'), $payload)->assertSessionHasErrors('household_id');
        $payload['household_id'] = $this->home->id;
        $this->post(route('secretary.certificates.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('barangay_certificates', 1);
        $this->mock(BarangayOfficialsRegistry::class)->shouldReceive('resolvedSecretaryName')->once()->andReturnUsing(function () {
            $this->people['active']->update(['resident_status' => 'deceased']);
            $this->people['unavailable']->update(['resident_status' => 'moved_out']);

            return $this->secretary->display_name;
        });
        $this->post(route('secretary.certificates.store'), $payload)->assertSessionHasErrors('household_id');
        $this->assertDatabaseCount('barangay_certificates', 1);
    }

    #[DataProvider('residentStates')]
    public function test_issued_certificate_history_remains_readable_after_recipient_changes(string $state, bool $eligible): void
    {
        $resident = $this->people['active'];
        $form = $this->get(route('secretary.certificates.create'))->assertOk();
        $this->post(route('secretary.certificates.store'), $this->certificatePayload($form->viewData('reviewToken')) + ['resident_id' => $resident->id])
            ->assertSessionHasNoErrors();
        $certificate = BarangayCertificate::firstOrFail();
        $snapshot = $certificate->getAttributes();
        if ($state === 'deleted') {
            $resident->delete();
        } else {
            $resident->update(['resident_status' => $state === 'unavailable' ? 'active' : $state, 'is_active' => $state !== 'unavailable']);
        }
        $this->assertSame($eligible, $resident->isCurrentPopulation());
        $this->get(route('secretary.certificates.show', $certificate))->assertOk()->assertSee($certificate->certificate_no);
        $this->get(route('secretary.certificates.pdf', $certificate))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('secretary.certificates.index'))->assertOk()->assertSee($certificate->certificate_no);
        $export = $this->get(route('secretary.certificates.export', ['format' => 'csv']))->assertOk();
        $this->assertStringContainsString($certificate->certificate_no, $export->streamedContent());
        $this->assertSame($snapshot, $certificate->fresh()->getAttributes());
    }

    private function certificatePayload(string $token): array
    {
        return ['certificate_type' => 'barangay_clearance', 'recipient_type' => 'resident', 'purpose' => 'Synthetic test',
            'issued_at' => now()->timezone('Asia/Manila')->format('Y-m-d\TH:i'), 'review_token' => $token];
    }

    private function pdfText(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'healthlink-current-output-');
        try {
            file_put_contents($path, $content);
            $process = new Process(['pdftotext', '-layout', $path, '-']);
            $process->mustRun();

            return $process->getOutput();
        } finally {
            unlink($path);
        }
    }
}
