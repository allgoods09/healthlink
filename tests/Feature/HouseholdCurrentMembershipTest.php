<?php

namespace Tests\Feature;

use App\Http\Controllers\Bns\HouseholdController;
use App\Http\Requests\Admin\Geometry\HouseholdUpdateRequest;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HouseholdCurrentMembershipTest extends TestCase
{
    use RefreshDatabase;

    private Household $home;

    private Resident $current;

    private Resident $unavailable;

    private array $historical = [];

    protected function setUp(): void
    {
        parent::setUp();
        $purok = Purok::factory()->create(['purok_number' => 1]);
        $this->home = Household::create(['purok_id' => $purok->id, 'household_no' => 'L3B-001',
            'household_address' => 'Synthetic household', 'is_active' => true]);
        $this->current = $this->member('Current');
        $this->unavailable = $this->member('CurrentUnavailable', ['is_active' => false]);
        foreach ([Resident::STATUS_DECEASED, Resident::STATUS_MOVED_OUT, Resident::STATUS_RELOCATED] as $status) {
            $this->historical[] = $this->member('Historical'.$status, ['resident_status' => $status]);
        }
        $deleted = $this->member('Deleted');
        $deleted->delete();
        $this->historical[] = $deleted;
        $this->home->update(['head_resident_id' => $this->unavailable->id]);
    }

    public static function householdRoles(): array
    {
        return [['secretary'], ['admin'], ['bhw']];
    }

    #[DataProvider('householdRoles')]
    public function test_household_directories_and_details_use_current_members_without_rewriting_history(string $role): void
    {
        $this->actingAs($this->user($role));
        $before = $this->registrySnapshot();
        $this->get(route($role.'.households.index'))->assertOk()
            ->assertViewHas('households', fn ($rows) => $rows->sole()->current_members_count === 2);
        $this->get(route($role.'.households.show', $this->home))->assertOk()
            ->assertSee('2 current members')->assertSee($this->unavailable->formal_name)
            ->assertSee('Historical Attached Residents (3)')->assertDontSee('Deleted,')
            ->assertViewHas('household', fn ($home) => $home->currentMembers->pluck('id')->sort()->values()->all()
                === [$this->current->id, $this->unavailable->id]);
        $this->assertSame(5, $this->home->residents()->count());
        $this->assertSame(6, $this->home->residents()->withTrashed()->count());
        $this->assertSame($before, $this->registrySnapshot());
    }

    #[DataProvider('householdRoles')]
    public function test_household_exports_match_current_member_counts(string $role): void
    {
        $csv = $this->actingAs($this->user($role))->get(route($role.'.households.export', ['format' => 'csv']))
            ->assertOk()->streamedContent();
        $rows = array_map('str_getcsv', explode("\n", trim($csv)));
        $this->assertCount(2, $rows);
        $this->assertSame('2', $rows[1][array_search('Residents', $rows[0], true)]);
    }

    public function test_edit_head_candidates_include_unavailable_active_and_exclude_historical_members(): void
    {
        foreach (['secretary', 'admin'] as $role) {
            $this->actingAs($this->user($role))->get(route($role.'.households.edit', $this->home))->assertOk()
                ->assertViewHas('household', fn ($home) => $home->currentMembers->pluck('id')->sort()->values()->all()
                    === [$this->current->id, $this->unavailable->id]);
        }
    }

    public function test_purok_and_barangay_details_count_current_members_without_changing_registered_households(): void
    {
        $this->actingAs($this->user('secretary'))->get(route('secretary.puroks.show', $this->home->purok))->assertOk()
            ->assertViewHas('totalResidents', 2)->assertViewHas('totalHouseholds', 1);
        $this->actingAs($this->user('admin'))->get(route('admin.puroks.show', $this->home->purok))->assertOk()
            ->assertViewHas('totalResidents', 2)->assertViewHas('totalHouseholds', 1);
        $this->get(route('admin.barangays.show', $this->home->purok->barangay))->assertOk()
            ->assertViewHas('totalResidents', 2)->assertViewHas('totalHouseholds', 1);
        // Other consumers retain the legacy aggregate contract until their approved slice.
        $this->assertSame(5, $this->home->purok->total_residents);
        $this->assertSame(5, $this->home->purok->barangay->total_residents);
    }

    public function test_historical_head_is_not_presented_as_current_or_silently_replaced(): void
    {
        $head = $this->historical[0];
        $this->home->update(['head_resident_id' => $head->id]);
        $before = $this->registrySnapshot();
        $this->actingAs($this->user('secretary'))->get(route('secretary.households.show', $this->home))->assertOk()
            ->assertSee('Recorded head (not current):')->assertSee($head->formal_name)->assertDontSee('Vacant');
        $this->assertNull($this->home->fresh()->currentHeadResident());
        $this->assertFalse($this->home->isVacant());
        $this->assertSame($head->id, $this->home->fresh()->head_resident_id);
        $this->assertSame($before, $this->registrySnapshot());
    }

    public function test_vacant_household_keeps_historical_head_context_without_status_writes(): void
    {
        $this->current->delete();
        $this->unavailable->delete();
        $this->home->update(['head_resident_id' => $this->historical[0]->id]);
        $before = $this->registrySnapshot();
        $this->actingAs($this->user('secretary'))->get(route('secretary.households.show', $this->home))->assertOk()
            ->assertSee('0 current members')->assertSee('Vacant')->assertSee('Recorded head (not current):');
        $this->assertTrue($this->home->fresh()->is_active);
        $this->assertSame($before, $this->registrySnapshot());
    }

    public function test_current_head_requires_same_household_and_handles_stale_loaded_relationship(): void
    {
        $this->home->load('headResident');
        $this->assertSame($this->unavailable->id, $this->home->currentHeadResident()->id);
        $this->home->update(['head_resident_id' => $this->current->id]);
        $this->assertSame($this->current->id, $this->home->currentHeadResident()->id);
        $other = Household::create(['purok_id' => $this->home->purok_id, 'household_no' => 'L3B-002',
            'household_address' => 'Other household', 'head_resident_id' => $this->current->id]);
        $this->assertNull($other->currentHeadResident());
        $this->home->update(['head_resident_id' => null]);
        $this->assertNull($this->home->currentHeadResident());
    }

    public function test_household_correction_proposals_use_current_head_candidates_for_bhw_and_phn(): void
    {
        foreach (['bhw', 'phn'] as $role) {
            $this->actingAs($this->user($role));
            $data = ['subject_id' => $this->home->id, 'purok_id' => $this->home->purok_id, 'household_no' => 'L3B-001',
                'household_address' => 'Synthetic household', 'request_reason' => 'Confirm current head'];
            $this->post(route($role.'.update-requests.store-household'), $data + ['head_resident_id' => $this->historical[0]->id])
                ->assertSessionHasErrors('head_resident_id');
            $this->post(route($role.'.update-requests.store-household'), $data + ['head_resident_id' => $this->unavailable->id])
                ->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertSame($this->unavailable->id, $this->home->fresh()->head_resident_id);
    }

    public function test_current_members_without_a_head_remain_occupied_and_are_not_automatically_assigned(): void
    {
        $this->home->update(['head_resident_id' => null]);
        $before = $this->registrySnapshot();
        $this->actingAs($this->user('secretary'))->get(route('secretary.households.show', $this->home))->assertOk()
            ->assertSee('Not assigned')->assertDontSee('Vacant');
        $this->assertSame($before, $this->registrySnapshot());
    }

    public function test_invalid_foreign_head_pointer_does_not_expose_foreign_resident_context(): void
    {
        $purok = Purok::factory()->create(['purok_number' => 1]);
        $foreign = Household::create(['purok_id' => $purok->id, 'household_no' => 'FOREIGN', 'household_address' => 'Other scope']);
        $resident = $this->member('ForeignContext', ['household_id' => $foreign->id]);
        $this->home->update(['head_resident_id' => $resident->id]);
        $this->actingAs($this->user('secretary'))->get(route('secretary.households.show', $this->home))->assertOk()
            ->assertSee('Not assigned')->assertDontSee($resident->formal_name);
        $this->assertSame($resident->id, $this->home->fresh()->head_resident_id);
    }

    public function test_shared_household_templates_preserve_deferred_bns_contract(): void
    {
        $user = $this->user('bns');
        $this->actingAs($user);
        app('request')->setUserResolver(fn () => $user);
        // Dormant BNS household routes must not be reintroduced just to test their shared contract.
        $controller = app(HouseholdController::class);
        $this->assertSame(5, $controller->index(Request::create('/'))->getData()['households']->sole()->residents_count);
        $this->assertSame(5, $controller->show($this->home)->getData()['household']->residents->count());
        $html = Blade::render("@include('households.partials.profile-details', ['currentMembership' => false])", ['household' => $this->home]);
        $this->assertStringContainsString('5 members', $html);
        $this->assertStringContainsString('Registered Members', $html);

        $request = HouseholdUpdateRequest::create('/legacy-household/'.$this->home->id, 'PUT', [
            'purok_id' => $this->home->purok_id, 'household_no' => 'L3B-001', 'household_address' => 'Synthetic household',
            'is_active' => 1, 'head_resident_id' => $this->historical[0]->id,
        ]);
        $route = new Route('PUT', '/legacy-household/{household}', fn () => null);
        $route->bind($request)->setParameter('household', $this->home);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $user);
        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);
        $this->assertTrue($validator->passes());
    }

    private function member(string $name, array $overrides = []): Resident
    {
        return Resident::create(array_replace(['household_id' => $this->home->id, 'first_name' => $name, 'last_name' => 'Synthetic',
            'birth_date' => '1990-01-01', 'birth_place' => 'Tubigon', 'sex' => 'Female', 'civil_status' => 'Single',
            'citizenship' => 'Filipino', 'relationship_to_head' => 'Child', 'resident_status' => Resident::STATUS_ACTIVE,
            'is_active' => true], $overrides));
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'assigned_barangay_id' => $role === 'admin' ? null : $this->home->purok->barangay_id,
            'assigned_purok_id' => $role === 'bhw' ? $this->home->purok_id : null]);
    }

    private function registrySnapshot(): array
    {
        return collect(['residents', 'households', 'resident_lifecycle_events', 'resident_code_sequences'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy($table === 'resident_code_sequences' ? 'origin_barangay_id' : 'id')->get()->toJson()])->all();
    }
}
