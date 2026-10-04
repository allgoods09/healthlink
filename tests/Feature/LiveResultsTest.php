<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Support\Nutrition\OptCycleWorkflow;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveResultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_async_and_native_get_use_identical_scoped_search_filter_order_and_pagination(): void
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id]);
        $household = Household::query()->create(['purok_id' => $purok->id, 'household_no' => '001', 'household_address' => 'Synthetic Test Address', 'is_active' => true]);
        $secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id]);
        for ($index = 0; $index < 18; $index++) $this->resident($household, 'CrisMatch'.$index);
        $this->resident($household, 'OtherChild');
        $foreignHousehold = Household::query()->create(['purok_id' => Purok::factory()->create()->id, 'household_no' => '001', 'household_address' => 'Outside Test Address', 'is_active' => true]);
        $this->resident($foreignHousehold, 'CrisOutside');
        $url = route('secretary.residents.index', ['search' => 'Cris', 'purok_id' => $purok->id, 'status' => 'active']);

        $native = $this->actingAs($secretary)->get($url)->assertOk();
        $async = $this->get($url, ['X-HealthLink-Live-Results' => '1', 'Accept' => 'text/html'])->assertOk();
        $this->assertSame($this->regions($native->getContent()), $this->regions($async->getContent()));
        $async->assertSee('CrisMatch')->assertDontSee('CrisOutside')->assertDontSee('OtherChild');
        $async->assertSee('data-live-results-form="admin-geometry-residents-index"', false);
        $async->assertSee('data-navigation-skeleton="generic"', false);
        $pageTwo = $this->get($url.'&page=2', ['X-HealthLink-Live-Results' => '1'])->assertOk();
        $this->assertNotSame($this->regions($native->getContent()), $this->regions($pageTwo->getContent()));
        $pageTwo->assertSee('search=Cris', false)->assertSee('purok_id='.$purok->id, false);
        $foreignPurok = Purok::factory()->create();
        $this->get(route('secretary.residents.index', ['purok_id' => $foreignPurok->id]), ['X-HealthLink-Live-Results' => '1'])
            ->assertOk()->assertDontSee('CrisMatch')->assertDontSee('CrisOutside');
    }

    public function test_async_header_does_not_bypass_authentication_or_role_boundaries(): void
    {
        $headers = ['X-HealthLink-Live-Results' => '1', 'Accept' => 'text/html'];
        $this->get(route('secretary.residents.index'), $headers)->assertRedirect(route('login'));
        $bhw = User::factory()->create(['role' => 'bhw', 'assigned_barangay_id' => Barangay::factory()->create()->id]);
        $this->actingAs($bhw)->get(route('admin.users.index'), $headers)->assertForbidden();
        $this->get(route('secretary.residents.index'), $headers)->assertForbidden();
    }

    public function test_representative_pages_keep_forms_outside_replaceable_fragments(): void
    {
        $barangay = Barangay::factory()->create();
        foreach ([
            'admin' => ['admin.users.index', 'admin.residents.index', 'admin.households.index', 'admin.reports.index', 'admin.oversight.nutrition', 'admin.archive.index'],
            'secretary' => ['secretary.team.index', 'secretary.drafts.index', 'secretary.update-requests.index', 'secretary.reports.demographics'],
            'bns' => ['bns.maternal.index', 'bns.watchlist.index', 'bns.opt-cycles.index', 'bns.feeding-programs.index'],
            'bhw' => ['bhw.households.index', 'bhw.residents.index'],
            'phn' => ['phn.follow-ups.index', 'phn.encounters.index', 'phn.residents.index'],
            'mho' => ['mho.escalations.index', 'mho.residents.index'],
        ] as $role => $routes) {
            $user = User::factory()->create(['role' => $role, 'assigned_barangay_id' => $barangay->id]);
            foreach ($routes as $route) {
                $response = $this->actingAs($user)->get(route($route), ['X-HealthLink-Live-Results' => '1'])->assertOk();
                $this->assertNotEmpty($this->regions($response->getContent()), $route);
            }
        }
    }

    public function test_opt_child_masterlist_keeps_sort_and_filter_forms_separate_from_results_and_completion(): void
    {
        $barangay = Barangay::factory()->create();
        $bns = User::factory()->create(['role' => 'bns', 'assigned_barangay_id' => $barangay->id]);
        $cycle = app(OptCycleWorkflow::class)->create($bns, ['year' => 2026, 'round' => 'july', 'reference_date' => '2026-07-01']);
        $url = route('bns.opt-cycles.show', [$cycle, 'search' => 'Cris', 'sort' => 'name', 'direction' => 'desc', 'measurement_status' => 'unmeasured']);
        $native = $this->actingAs($bns)->get($url)->assertOk();
        $async = $this->get($url, ['X-HealthLink-Live-Results' => '1'])->assertOk();
        $this->assertSame($this->regions($native->getContent()), $this->regions($async->getContent()));
        $async->assertSee('data-live-results-form="bns-opt-cycles-show"', false)
            ->assertSee('name="sort"', false)->assertSee('name="completion_note"', false);
    }

    private function regions(string $html): array
    {
        $document = new DOMDocument;
        @$document->loadHTML($html);
        $xpath = new DOMXPath($document);
        $regions = [];
        foreach ($xpath->query('//*[@data-live-results]') as $region) {
            $this->assertSame(0, $xpath->query('.//form[translate(@method, "get", "GET")="GET"]', $region)->length);
            $this->assertSame(0, $xpath->query('.//*[@data-live-results]', $region)->length);
            $regions[] = $document->saveHTML($region);
        }

        return $regions;
    }

    private function resident(Household $household, string $name): Resident
    {
        return Resident::query()->create([
            'household_id' => $household->id, 'first_name' => $name, 'last_name' => 'Pilot',
            'birth_date' => '2000-01-01', 'birth_place' => 'Tubigon', 'sex' => 'Female',
            'civil_status' => 'Single', 'citizenship' => 'Filipino', 'relationship_to_head' => 'Daughter',
            'resident_status' => Resident::STATUS_ACTIVE, 'is_active' => true,
        ]);
    }
}
