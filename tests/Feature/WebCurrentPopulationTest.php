<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BarangayCertificate;
use App\Models\ClinicalEncounter;
use App\Models\FeedingProgram;
use App\Models\FieldVisit;
use App\Models\Household;
use App\Models\InfantFeedingLog;
use App\Models\MicronutrientSupplementationLog;
use App\Models\OptMeasurement;
use App\Models\ProfileUpdateRequest;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\TriageRecord;
use App\Models\User;
use App\Support\MobileBootstrapPayload;
use App\Support\Nutrition\OptCycleWorkflow;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebCurrentPopulationTest extends TestCase
{
    use RefreshDatabase;

    private Household $home;

    private array $people;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 7, 15)->startOfDay());
        $this->home = $this->household(Purok::factory()->create(), '001');
        foreach (self::states() as [$state]) {
            $this->people[$state] = $this->person($this->home, $state);
        }
    }

    public static function states(): array
    {
        return [['active', true], ['unavailable', true], ['deceased', false], ['moved_out', false],
            ['relocated', false], ['deleted', false]];
    }

    public static function roles(): array
    {
        return [['secretary'], ['admin'], ['bhw'], ['phn'], ['mho']];
    }

    public static function workflows(): array
    {
        $cases = [];
        foreach (['triage', 'encounter', 'feeding', 'maternal', 'infant', 'supplement', 'flag'] as $workflow) {
            foreach (self::states() as [$state, $current]) {
                $cases[$workflow.' '.$state] = [$workflow, $state, $current];
            }
        }

        return $cases;
    }

    private function household(Purok $purok, string $number): Household
    {
        return Household::create(['purok_id' => $purok->id, 'household_no' => $number,
            'household_address' => 'Synthetic test street', 'is_active' => true]);
    }

    private function person(Household $household, string $state, string $prefix = ''): Resident
    {
        $resident = Resident::create(['household_id' => $household->id, 'first_name' => $prefix.ucfirst($state).'Sentinel',
            'last_name' => 'Pilot', 'birth_date' => '2025-01-01', 'birth_place' => 'Tubigon',
            'sex' => $state === 'active' ? 'Male' : 'Female', 'civil_status' => 'Single', 'citizenship' => 'Filipino',
            'relationship_to_head' => 'Child', 'is_active' => $state !== 'unavailable',
            'resident_status' => in_array($state, ['unavailable', 'deleted'], true) ? 'active' : $state]);
        if ($state === 'deleted') {
            $resident->delete();
        }

        return $resident;
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'assigned_barangay_id' => $this->home->purok->barangay_id,
            'assigned_purok_id' => $role === 'bhw' ? $this->home->purok_id : null]);
    }

    private function csv(string $url): string
    {
        return $this->get($url)->assertOk()->streamedContent();
    }

    #[DataProvider('roles')]
    public function test_current_and_historical_directory_modes_match_live_results_and_exports(string $role): void
    {
        $foreign = $this->person($this->household(Purok::factory()->create(), '001'), 'active', 'Foreign');
        $this->actingAs($this->user($role));
        $scoped = in_array($role, ['secretary', 'bhw'], true);
        foreach (['' => ['active', 'unavailable'], 'active' => ['active', 'unavailable'], 'deceased' => ['deceased'],
            'moved_out' => ['moved_out'], 'relocated' => ['relocated'], 'all' => ['active', 'unavailable', 'deceased', 'moved_out', 'relocated']] as $mode => $states) {
            $params = ['resident_status' => $mode, 'search' => 'Sentinel'];
            $url = route($role.'.residents.index', $params);
            $native = $this->get($url)->assertOk();
            $live = $this->get($url, ['X-HealthLink-Live-Results' => '1'])->assertOk();
            $expected = array_map(fn ($state) => $this->people[$state]->id, $states);
            if (! $scoped && in_array($mode, ['', 'active', 'all'], true)) {
                $expected[] = $foreign->id;
            }
            $this->assertEqualsCanonicalizing($expected, $native->viewData('residents')->modelKeys());
            $this->assertSame($native->viewData('residents')->modelKeys(), $live->viewData('residents')->modelKeys());
            $csv = $this->csv(route($role.'.residents.export', $params + ['format' => 'csv']));
            foreach ($this->people as $state => $resident) {
                $this->assertSame(in_array($state, $states, true), str_contains($csv, $resident->first_name), $role.' '.$mode.' '.$state);
            }
            if ($scoped) {
                $this->assertStringNotContainsString($foreign->first_name, $csv);
            }
            if ($mode === 'relocated') {
                $this->assertStringContainsString('Relocated (Legacy)', $csv);
            }
        }
        if ($scoped) {
            $this->get(route($role.'.residents.show', $foreign))->assertStatus($role === 'secretary' ? 403 : 404);
        }
    }

    public function test_archive_mode_remains_separate_from_all_and_availability_is_explicit_only(): void
    {
        foreach (['secretary', 'admin'] as $role) {
            $this->actingAs($this->user($role));
            $all = $this->get(route($role.'.residents.index', ['lifecycle' => 'all']))->assertOk();
            $this->assertCount(5, $all->viewData('residents'));
            $archived = $this->get(route($role.'.residents.index', ['lifecycle' => 'deleted']))->assertOk();
            $this->assertSame([$this->people['deleted']->id], $archived->viewData('residents')->modelKeys());
            $legacy = $this->get(route($role.'.residents.index', ['status' => 'inactive']))->assertOk();
            $this->assertSame([$this->people['unavailable']->id], $legacy->viewData('residents')->modelKeys());
        }
    }

    public function test_directory_export_ignores_pagination_and_read_queries_do_not_mutate_lifecycle_state(): void
    {
        for ($i = 0; $i < 17; $i++) {
            $this->person($this->home, 'unavailable', 'Extra'.$i);
        }
        $before = DB::table('residents')->orderBy('id')->get()->toJson();
        $events = DB::table('resident_lifecycle_events')->count();
        $this->actingAs($this->user('secretary'));
        $response = $this->get(route('secretary.residents.index'))->assertOk();
        $this->assertSame(19, $response->viewData('residents')->total());
        $csv = $this->csv(route('secretary.residents.export', ['format' => 'csv', 'page' => 2]));
        for ($i = 0; $i < 17; $i++) {
            $this->assertStringContainsString('Extra'.$i.'UnavailableSentinel', $csv);
        }
        $this->assertSame($before, DB::table('residents')->orderBy('id')->get()->toJson());
        $this->assertSame($events, DB::table('resident_lifecycle_events')->count());
    }

    public function test_dashboard_demographics_and_geographic_exports_agree_on_current_population(): void
    {
        $this->people['unavailable']->update(['birth_date' => '1945-01-01']);
        $vacant = $this->household($this->home->purok, '002');
        $this->person($vacant, 'deceased', 'Historical');
        $this->actingAs($this->user('secretary'));
        $dashboard = $this->get(route('secretary.dashboard'))->assertOk();
        $this->assertSame(2, $dashboard->viewData('activeResidents'));
        $this->assertSame(1, $dashboard->viewData('seniorCount'));
        $this->assertSame(1, $dashboard->viewData('minorCount'));
        $this->assertSame(1, $dashboard->viewData('occupiedHouseholdCount'));
        $this->assertSame(1, $dashboard->viewData('vacantHouseholdCount'));
        $this->assertSame(2, $dashboard->viewData('purokDensity')->first()['active_residents']);
        $this->assertSame(1, $dashboard->viewData('purokDensity')->first()['households']);
        // Old bookmarked filters cannot redefine a current demographic report.
        $report = $this->get(route('secretary.reports.demographics', ['status' => 'active', 'resident_status' => 'all']))->assertOk();
        $summary = $report->viewData('summary');
        $this->assertSame(2, $summary['residents']);
        $this->assertSame(1, $summary['male']);
        $this->assertSame(1, $summary['female']);
        $this->assertSame(2, $report->viewData('byPurok')->sum('residents'));
        $csv = $this->csv(route('secretary.reports.demographics.export', ['format' => 'csv']));
        $this->assertStringContainsString('UnavailableSentinel', $csv);
        $this->assertStringNotContainsString('DeceasedSentinel', $csv);
        $this->actingAs($this->user('admin'));
        $admin = $this->get(route('admin.dashboard'))->assertOk();
        $this->assertSame(2, $admin->viewData('currentResidents'));
        $this->assertSame(6, $admin->viewData('totalResidents'));
        $this->assertSame(1, $admin->viewData('occupiedHouseholds'));
        $this->assertSame(1, $admin->viewData('vacantHouseholds'));
        $municipal = $this->get(route('admin.reports.index'))->assertOk();
        $row = $municipal->viewData('reports')->firstWhere('key', 'demographics')['rows']->first();
        $this->assertSame(2, $row['active_resident_count']);
        $this->assertSame(1, $row['male_resident_count']);
        $this->assertSame(1, $row['female_resident_count']);
        $this->assertSame(1, $row['occupied_household_count']);
        $this->assertSame(1, $row['vacant_household_count']);
        $export = $this->csv(route('admin.reports.export', ['report' => 'demographics', 'format' => 'csv']));
        $this->assertStringContainsString('Occupied Households', $export);
        $this->actingAs($this->user('phn'));
        $phn = $this->get(route('phn.dashboard'))->assertOk();
        $this->assertSame(2, $phn->viewData('workloadBreakdown')->first()['active_residents_count']);
    }

    #[DataProvider('workflows')]
    public function test_new_participation_accepts_current_including_unavailable_and_rejects_history(string $workflow, string $state, bool $current): void
    {
        $resident = $this->people[$state];
        $role = match ($workflow) {
            'triage', 'flag' => 'bhw', 'encounter' => 'phn', default => 'bns'
        };
        $user = $this->user($role);
        $this->actingAs($user);
        $payload = ['resident_id' => $resident->id];
        if ($workflow === 'maternal') {
            $resident->update(['sex' => 'Female', 'birth_date' => '1995-01-01']);
        }
        $url = match ($workflow) {
            'triage' => route('bhw.triage.store'),
            'encounter' => route('phn.encounters.store'),
            'feeding' => route('bns.feeding-programs.enrollments.store', $this->program($user)),
            'maternal' => route('bns.maternal.profile.store'),
            'infant' => route('bns.maternal.infant-feeding.store', $this->mother()),
            'supplement' => route('bns.micronutrients.store'),
            'flag' => route('bhw.nutrition-flags.store'),
        };
        $payload += match ($workflow) {
            'triage' => ['measured_at' => now()->subMinute()->format('Y-m-d H:i:s'), 'triage_notes' => 'Synthetic observation'],
            'encounter' => ['encountered_at' => now()->subMinute()->format('Y-m-d H:i:s'), 'consultation_notes' => 'Synthetic consultation'],
            'feeding' => ['enrolled_on' => '2026-07-14'],
            'maternal' => ['is_currently_pregnant' => true],
            'infant' => ['observed_on' => '2026-07-14', 'feeding_method' => InfantFeedingLog::METHOD_MIXED_FEEDING],
            'supplement' => ['administered_on' => '2026-07-14', 'supplement_type' => MicronutrientSupplementationLog::TYPE_VITAMIN_A,
                'recipient_category' => MicronutrientSupplementationLog::RECIPIENT_TODDLER],
            'flag' => ['flag_reason' => 'Synthetic nutrition reference'],
        };
        $response = $this->post($url, $payload);
        if ($current) {
            $response->assertRedirect()->assertSessionHasNoErrors();
        } else {
            $response->assertSessionHasErrors('resident_id');
        }
    }

    public function test_purok_availability_does_not_hide_current_population_from_geographic_metrics(): void
    {
        $purok = Purok::factory()->create(['barangay_id' => $this->home->purok->barangay_id,
            'purok_number' => $this->home->purok->purok_number + 1, 'is_active' => false]);
        $person = $this->person($this->household($purok, '001'), 'unavailable', 'InactivePurok');
        $this->actingAs($this->user('secretary'));
        $dashboard = $this->get(route('secretary.dashboard'))->assertOk();
        $this->assertSame(3, $dashboard->viewData('activeResidents'));
        $this->assertSame(3, $dashboard->viewData('purokDensity')->sum('active_residents'));
        $report = $this->get(route('secretary.reports.demographics'))->assertOk();
        $this->assertSame(3, $report->viewData('summary')['residents']);
        $this->assertSame(3, $report->viewData('byPurok')->sum('residents'));
        $this->assertStringContainsString($person->first_name, $this->csv(route('secretary.reports.demographics.export', ['format' => 'csv'])));
    }

    private function program(User $user): FeedingProgram
    {
        return FeedingProgram::create(['barangay_id' => $this->home->purok->barangay_id, 'created_by_user_id' => $user->id,
            'name' => 'Synthetic program', 'program_status' => 'active', 'starts_on' => '2026-07-01']);
    }

    private function mother(): Resident
    {
        $mother = $this->person($this->home, 'active', 'Mother');
        $mother->update(['sex' => 'Female', 'birth_date' => '1995-01-01']);

        return $mother;
    }

    public function test_new_opt_roster_uses_canonical_population_but_captured_entries_stay_historical(): void
    {
        $bns = $this->user('bns');
        $workflow = app(OptCycleWorkflow::class);
        $cycle = $workflow->create($bns, ['year' => 2026, 'round' => 'july', 'reference_date' => '2026-07-01']);
        $this->assertEqualsCanonicalizing([$this->people['active']->id, $this->people['unavailable']->id], $cycle->entries->pluck('resident_key')->all());
        $before = $cycle->entries->toJson();
        $this->people['unavailable']->update(['resident_status' => 'moved_out']);
        $this->actingAs($bns);
        $this->get(route('bns.opt-cycles.show', $cycle))->assertOk()->assertSee('UnavailableSentinel');
        $csv = $this->csv(route('bns.opt-cycles.export', [$cycle, 'format' => 'csv']));
        $this->assertStringContainsString('UnavailableSentinel', $csv);
        $this->assertSame($before, $cycle->fresh()->entries->toJson());
    }

    #[DataProvider('states')]
    public function test_new_registered_caregiver_selection_uses_canonical_population(string $state, bool $current): void
    {
        $caregiver = $this->people[$state];
        $caregiver->update(['birth_date' => '1995-01-01']);
        $bns = $this->user('bns');
        $workflow = app(OptCycleWorkflow::class);
        $cycle = $workflow->create($bns, ['year' => 2026, 'round' => 'july', 'reference_date' => '2026-07-01']);
        $entry = $cycle->entries()->where('resident_key', $this->people[$state === 'active' ? 'unavailable' : 'active']->id)->firstOrFail();
        $page = $this->actingAs($bns)->get(route('bns.opt-cycles.entry', [$cycle, $entry]))->assertOk();
        $this->assertSame($current, $page->viewData('caregivers')->contains('id', $caregiver->id));
        if (! $current) {
            $this->expectException(ModelNotFoundException::class);
        }
        $workflow->confirmProfile($bns, $cycle, $entry, ['caregiver_resident_id' => $caregiver->id,
            'caregiver_relationship' => 'Guardian', 'ip_membership' => 'unknown']);
        $this->assertSame($caregiver->id, $entry->resident->childNutritionProfile->caregiver_resident_id);
    }

    public function test_new_selection_options_use_canonical_population_and_keep_role_scope(): void
    {
        $foreign = $this->person($this->household(Purok::factory()->create(), '001'), 'active', 'Foreign');
        $this->actingAs($this->user('bhw'));
        $triage = $this->get(route('bhw.triage.create', ['resident_id' => $this->people['deceased']->id]))->assertOk();
        $this->assertNull($triage->viewData('selectedResident'));
        $this->assertEqualsCanonicalizing([$this->people['active']->id, $this->people['unavailable']->id], $triage->viewData('residentOptions')->modelKeys());
        $this->post(route('bhw.triage.store'), ['resident_id' => $foreign->id, 'measured_at' => '2026-07-14 12:00:00',
            'triage_notes' => 'Foreign'])->assertSessionHasErrors('resident_id');
        $this->actingAs($this->user('phn'));
        $encounter = $this->get(route('phn.encounters.create'))->assertOk();
        $this->assertTrue($encounter->viewData('residentOptions')->contains('id', $this->people['unavailable']->id));
        $this->assertFalse($encounter->viewData('residentOptions')->contains('id', $this->people['deceased']->id));
        $this->actingAs($this->user('bns'));
        $supplements = $this->get(route('bns.micronutrients.create'))->assertOk();
        $this->assertEqualsCanonicalizing([$this->people['active']->id, $this->people['unavailable']->id], $supplements->viewData('residentOptions')->modelKeys());
    }

    public static function historicalStates(): array
    {
        return [['deceased'], ['moved_out'], ['relocated']];
    }

    #[DataProvider('historicalStates')]
    public function test_existing_health_visits_certificates_and_corrections_remain_accessible(string $state): void
    {
        $resident = $this->people['unavailable'];
        $bhw = $this->user('bhw');
        $bns = $this->user('bns');
        $phn = $this->user('phn');
        $secretary = $this->user('secretary');
        $this->actingAs($bhw)->post(route('bhw.triage.store'), ['resident_id' => $resident->id,
            'measured_at' => '2026-07-14 12:00:00', 'triage_notes' => 'Historical triage sentinel'])->assertSessionHasNoErrors();
        $triage = TriageRecord::firstOrFail();
        $this->actingAs($phn)->post(route('phn.encounters.store'), ['resident_id' => $resident->id,
            'encountered_at' => '2026-07-14 12:05:00', 'consultation_notes' => 'Historical consultation sentinel',
            'is_escalated_to_mho' => true, 'escalation_notes' => 'Historical review',
            'follow_up_date' => '2026-07-15', 'follow_up_status' => 'due'])->assertSessionHasNoErrors();
        $encounter = ClinicalEncounter::firstOrFail();
        $program = $this->program($bns);
        $this->actingAs($bns)->post(route('bns.feeding-programs.enrollments.store', $program),
            ['resident_id' => $resident->id, 'enrolled_on' => '2026-07-14'])->assertSessionHasNoErrors();
        $measurement = OptMeasurement::create(['resident_id' => $resident->id, 'barangay_id' => $this->home->purok->barangay_id,
            'measured_by_user_id' => $bns->id, 'measurement_date' => '2026-07-01', 'age_in_months' => 18,
            'sex_snapshot' => 'Female', 'weight_kg' => 10, 'height_cm' => 80, 'measurement_posture' => 'standing']);
        $visit = FieldVisit::create(['mobile_uuid' => '00000000-0000-4000-8000-000000000777',
            'household_id' => $this->home->id, 'recorded_by_user_id' => $bhw->id,
            'visited_at' => '2026-07-14 09:00:00', 'notes' => 'Historical visit sentinel', 'photos' => [], 'source' => 'mobile']);
        $certificate = BarangayCertificate::create(['barangay_id' => $this->home->purok->barangay_id,
            'certificate_type' => 'barangay_clearance', 'recipient_type' => 'resident', 'resident_id' => $resident->id,
            'certificate_no' => 'HISTORY-001', 'issued_to_name' => $resident->formal_name, 'purpose' => 'Historical issuance',
            'issued_at' => '2026-07-14 12:00:00', 'issued_by_user_id' => $secretary->id,
            'signatory_name_at_issuance' => $secretary->display_name]);
        $correction = ProfileUpdateRequest::create(['barangay_id' => $this->home->purok->barangay_id,
            'submitted_by_user_id' => $bhw->id, 'subject_type' => 'resident', 'subject_id' => $resident->id,
            'current_snapshot' => ['first_name' => $resident->first_name], 'proposed_changes' => ['contact_number' => '09123456789'],
            'request_reason' => 'Historical correction sentinel', 'request_status' => 'pending']);
        AuditLog::logMutation('created', $secretary, $resident);
        $resident->update(['resident_status' => $state]);
        $this->actingAs($bhw)->get(route('bhw.triage.show', $triage))->assertOk()->assertSee('Historical triage sentinel');
        $this->get(route('bhw.triage.edit', $triage))->assertOk();
        $this->put(route('bhw.triage.update', $triage), ['measured_at' => '2026-07-14 12:00:00',
            'triage_notes' => 'Corrected historical triage'])->assertSessionHasNoErrors();
        $this->actingAs($phn)->get(route('phn.encounters.show', $encounter))->assertOk()->assertSee('Historical consultation sentinel');
        $this->get(route('phn.triage.show', $triage))->assertOk()->assertSee('Corrected historical triage');
        $create = $this->get(route('phn.encounters.create', ['triage_record_id' => $triage->id]))->assertOk();
        $this->assertNull($create->viewData('selectedTriage'));
        $this->assertFalse($create->viewData('triageOptions')->contains('id', $triage->id));
        $this->get(route('phn.follow-ups.index'))->assertOk()->assertSee('UnavailableSentinel');
        $this->actingAs($this->user('mho'))->get(route('mho.escalations.show', $encounter))->assertOk();
        $this->actingAs($bns)->get(route('bns.opt-measurements.show', $measurement))->assertOk()->assertSee('UnavailableSentinel');
        $this->get(route('bns.feeding-programs.show', $program))->assertOk()->assertSee('UnavailableSentinel');
        $this->actingAs($secretary)->get(route('secretary.visits.show', $visit))->assertOk()->assertSee('Historical visit sentinel');
        $this->get(route('secretary.certificates.show', $certificate))->assertOk();
        $this->get(route('secretary.certificates.pdf', $certificate))->assertOk();
        $this->get(route('secretary.update-requests.show', $correction))->assertOk()->assertSee('Historical correction sentinel');
        $dashboard = $this->get(route('secretary.dashboard'))->assertOk();
        $this->assertTrue($dashboard->viewData('recentActivity')->contains(fn ($log) => $log->model_type === Resident::class && $log->model_id === $resident->id));
    }

    public function test_mobile_bootstrap_keeps_current_assigned_purok_contract_after_web_reads(): void
    {
        $bhw = $this->user('bhw');
        $bootstrap = app(MobileBootstrapPayload::class);
        $before = $bootstrap->build($bhw);
        $this->assertCount(2, $before['residents']);
        $this->assertEqualsCanonicalizing(array_map(fn ($state) => $this->people[$state]->id,
            ['active', 'unavailable']), array_column($before['residents'], 'id'));
        $this->assertSame(2, $before['resident_contract_version']);
        $this->actingAs($bhw)->get(route('bhw.residents.index'))->assertOk();
        $this->assertSame($before, $bootstrap->build($bhw));
    }

    #[DataProvider('historicalStates')]
    public function test_existing_maternal_history_remains_visible_and_correctable(string $state): void
    {
        $mother = $this->mother();
        $this->actingAs($this->user('bns'));
        $this->post(route('bns.maternal.profile.store'), ['resident_id' => $mother->id,
            'is_currently_pregnant' => true, 'current_risk_notes' => 'Historical maternal sentinel'])->assertSessionHasNoErrors();
        $mother->update(['resident_status' => $state]);
        $this->get(route('bns.maternal.show', $mother))->assertOk()->assertSee('Historical maternal sentinel');
        $this->put(route('bns.maternal.profile.update', $mother), ['is_currently_pregnant' => false,
            'is_currently_lactating' => false, 'current_risk_notes' => 'Historical correction'])->assertSessionHasNoErrors();
        $this->get(route('bns.maternal.index'))->assertOk()->assertSee($mother->first_name);
        $this->assertStringContainsString($mother->first_name, $this->csv(route('bns.maternal.export', ['format' => 'csv'])));
    }
}
