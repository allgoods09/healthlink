<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Support\HouseholdMemberOrdering;
use App\Support\RbiTemplatePdfGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class HouseholdPresentationTest extends TestCase
{
    use RefreshDatabase;

    private Household $home;

    private User $secretary;

    protected function setUp(): void
    {
        parent::setUp();
        $purok = Purok::factory()->create(['barangay_id' => Barangay::factory()->create()->id, 'purok_number' => 1]);
        $this->home = Household::create(['purok_id' => $purok->id, 'household_no' => '001', 'household_address' => 'Synthetic pilot street', 'is_active' => true]);
        $this->secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $purok->barangay_id]);
        $this->actingAs($this->secretary);
    }

    private function member(string $name, string $relationship, string $dob = '1990-01-01'): Resident
    {
        return Resident::create(['household_id' => $this->home->id, 'first_name' => $name, 'last_name' => 'Pilot',
            'birth_date' => $dob, 'birth_place' => 'Tubigon', 'sex' => 'Male', 'civil_status' => 'Single',
            'citizenship' => 'Filipino', 'relationship_to_head' => $relationship, 'resident_status' => 'active', 'is_active' => true]);
    }

    public function test_edit_initial_values_preserve_purok_and_old_input_without_writing(): void
    {
        $before = $this->home->fresh()->getAttributes();
        $this->get(route('secretary.households.edit', $this->home))->assertOk()
            ->assertSee("', '{$this->home->purok_id}',", false)
            ->assertSee(':selected="String(purok.id) === String(purokId)"', false);
        $other = Purok::factory()->create(['barangay_id' => $this->secretary->assigned_barangay_id, 'purok_number' => 2]);
        $this->withSession(['_old_input' => ['purok_id' => (string) $other->id]])
            ->get(route('secretary.households.edit', $this->home))->assertOk()->assertSee("', '{$other->id}',", false);
        $this->assertSame($before, $this->home->fresh()->getAttributes());
    }

    public function test_purok_changes_keep_existing_barangay_authorization(): void
    {
        $foreign = Purok::factory()->create();
        $this->put(route('secretary.households.update', $this->home), ['purok_id' => $foreign->id,
            'household_no' => '001', 'household_address' => 'Unchanged'])->assertSessionHasErrors('purok_id');
        $this->assertSame($this->home->purok_id, $this->home->fresh()->purok_id);
        $other = Purok::factory()->create(['barangay_id' => $this->secretary->assigned_barangay_id, 'purok_number' => 2]);
        $this->put(route('secretary.households.update', $this->home), ['purok_id' => $other->id,
            'household_no' => '001', 'household_address' => 'Moved within scope', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertSame($other->id, $this->home->fresh()->purok_id);
    }

    public function test_relationship_review_shows_name_sex_and_current_derived_age(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 4));
        $member = $this->member('Reviewed', 'Child', '1989-10-05');
        $candidate = $this->member('NewHead', 'Spouse');
        $response = $this->put(route('secretary.residents.update', $candidate), $candidate->only([
            'household_id', 'first_name', 'last_name', 'birth_place', 'sex', 'civil_status', 'citizenship', 'relationship_to_head', 'resident_status',
        ]) + ['birth_date' => '1990-01-01', 'set_as_household_head' => 1])->assertOk();
        $response->assertSee($member->full_name)->assertSee('Male')->assertSee('36 years old');
        $this->assertNull($this->home->fresh()->head_resident_id);
    }

    public function test_review_missing_demographics_use_safe_context_without_fake_age(): void
    {
        $member = $this->member('Missing', 'Child');
        $member->setRawAttributes(array_replace($member->getAttributes(), ['sex' => null, 'birth_date' => null]));
        $home = $this->home->setRelation('residents', collect([$member]))->setRelation('currentMembers', collect([$member]));
        $source = file_get_contents(resource_path('views/households/head-review.blade.php'));
        $source = substr($source, strpos($source, '<form'));
        $source = substr($source, 0, strrpos($source, '@endsection'));
        $html = Blade::render($source, ['errors' => new ViewErrorBag, 'payload' => [], 'action' => '/', 'method' => 'POST',
            'token' => 'fixture', 'cancelUrl' => '/', 'households' => collect([$home->id => $home]),
            'plans' => [$home->id => ['candidate_id' => null, 'candidate_name' => 'New head']]]);
        $this->assertStringContainsString('Sex not recorded', $html);
        $this->assertStringContainsString('Age not recorded', $html);
        $this->assertStringNotContainsString('0 years old', $html);
    }

    public function test_household_details_uses_shared_order_without_changing_global_listing(): void
    {
        $unknown = $this->member('AlphaUnknown', 'Head');
        $young = $this->member('BetaYoung', 'Daughter', '2020-01-01');
        $old = $this->member('GammaOlder', 'Child', '2010-01-01');
        $spouse = $this->member('DeltaSpouse', 'Spouse');
        $head = $this->member('ZetaHead', 'Friend');
        $this->home->update(['head_resident_id' => $head->id]);
        foreach ([$this->secretary, User::factory()->create(['role' => 'admin'])] as $user) {
            $this->actingAs($user)->get(route($user->role.'.households.show', $this->home))->assertOk()
                ->assertSeeInOrder([$head->full_name, $spouse->full_name, $old->full_name, $young->full_name, $unknown->full_name]);
        }
        $this->actingAs($this->secretary)->get(route('secretary.residents.index'))->assertOk()
            ->assertViewHas('residents', fn ($rows) => $rows->pluck('id')->all() === [$head->id, $spouse->id, $old->id, $young->id, $unknown->id]);
    }

    public function test_active_export_headings_change_without_changing_relationship_values(): void
    {
        $member = $this->member('ExportPerson', 'Child');
        foreach ([[$this->secretary, 'secretary.residents.export', []],
            [$this->secretary, 'secretary.reports.demographics.export', ['dataset' => 'roster']],
            [User::factory()->create(['role' => 'admin']), 'admin.residents.export', []]] as [$user, $route, $query]) {
            $csv = $this->actingAs($user)->get(route($route, ['format' => 'csv', 'search' => 'ExportPerson'] + $query))->assertOk()->streamedContent();
            $rows = array_map('str_getcsv', explode("\n", trim($csv)));
            $this->assertCount(2, $rows);
            $this->assertNotContains('Relationship', $rows[0]);
            $position = array_search('Relationship to Household Head', $rows[0], true);
            $this->assertNotFalse($position);
            $this->assertSame('Child', $rows[1][$position]);
            $expectedHeaders = isset($query['dataset'])
                ? ['Resident', 'Sex', 'Age', 'Birth Date', 'Purok', 'Household', 'Relationship to Household Head', 'Availability', 'Civil Registry Status', 'Occupation']
                : ['Resident', 'Sex', 'Birth Date', 'Age', 'Barangay', 'Purok', 'Household', 'Relationship to Household Head', 'Education', 'Occupation', 'Availability', 'Civil Status'];
            $this->assertSame($expectedHeaders, $rows[0]);
            $xlsx = $this->get(route($route, ['format' => 'xlsx', 'search' => 'ExportPerson'] + $query))->assertOk();
            $workbook = IOFactory::load($xlsx->baseResponse->getFile()->getPathname());
            $sheet = $workbook->getSheetByName('Data')->toArray();
            $this->assertSame($expectedHeaders, $sheet[0]);
            $this->assertSame('Child', $sheet[1][$position]);
            $workbook->disconnectWorksheets();
        }
        $this->assertSame('Child', $member->fresh()->relationship_to_head);
    }

    public function test_real_form_a_sorts_before_chunking_and_preserves_locked_templates_and_form_b_order(): void
    {
        $members = collect();
        foreach (range(1, 14) as $number) {
            $members->push($this->member(sprintf('Child%02d', $number), 'Child', sprintf('%04d-01-01', 2000 + $number)));
        }
        $head = $this->member('ZetaHead', 'Friend');
        $members[12]->update(['is_active' => false, 'resident_status' => 'deceased']);
        $members[13]->update(['is_active' => false, 'resident_status' => 'relocated']);
        $this->home->update(['head_resident_id' => $head->id]);
        $this->home->load('residents.socioEconomicProfile', 'purok.barangay', 'headResident');
        $generator = app(RbiTemplatePdfGenerator::class);
        $path = tempnam(sys_get_temp_dir(), 'healthlink-form-a-');
        try {
            $pdf = $generator->generateHouseholds([$this->home], ['officials' => ['barangay_secretary_name' => 'FinalPageSecretary']]);
            file_put_contents($path, $pdf);
            $text = new Process(['pdftotext', '-layout', $path, '-']);
            $text->mustRun();
            $pages = array_values(array_filter(explode("\f", $text->getOutput()), fn ($page) => trim($page) !== ''));
            $this->assertCount(2, $pages);
            $ordered = HouseholdMemberOrdering::ordered($this->home)->chunk(12);
            foreach ($pages as $index => $page) {
                $previous = -1;
                foreach ($ordered[$index] as $resident) {
                    $position = strpos($page, $resident->first_name);
                    $this->assertNotFalse($position);
                    $this->assertGreaterThan($previous, $position);
                    $previous = $position;
                }
                foreach ($ordered[1 - $index] as $otherPageMember) {
                    if ($otherPageMember->id !== $head->id) {
                        $this->assertStringNotContainsString($otherPageMember->first_name, $page);
                    }
                }
            }
            $this->assertStringNotContainsString('FinalPageSecretary', $pages[0]);
            $this->assertStringContainsString('FinalPageSecretary', $pages[1]);
            if ($artifact = getenv('HEALTHLINK_FORM_A_FIXTURE')) {
                file_put_contents($artifact, $pdf);
            }
            file_put_contents($path, $generator->generateResidents([$members[1], $members[0]]));
            $text->mustRun();
            $formB = $text->getOutput();
            $this->assertLessThan(strpos($formB, 'Child01'), strpos($formB, 'Child02'));
            $this->assertSame('f7f7ceb03d67eecf6b9ca4f5fa9f396e7625a492b21d4f777d0b7539436ad717', hash_file('sha256', resource_path('document-templates/rbi-form-a-household.pdf')));
            $this->assertSame('cdaa60e0572cd3f407e485d726c06ea0add8101f90565e8112079f1b319cfc95', hash_file('sha256', resource_path('document-templates/rbi-form-b-resident.pdf')));
        } finally {
            unlink($path);
        }
    }
}
