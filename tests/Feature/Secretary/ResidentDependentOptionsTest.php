<?php

namespace Tests\Feature\Secretary;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResidentDependentOptionsTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roles')]
    public function test_household_helper_preserves_contract_order_active_filter_and_scope(string $role): void
    {
        [$user, $purok, $household] = $this->fixture($role);
        $second = $this->household($purok, '002');
        $inactive = $this->household($purok, '003', false);
        $deleted = $this->household($purok, '004');
        $deleted->delete();
        $foreign = $this->household(Purok::factory()->create(), '001');
        $this->actingAs($user)->getJson(route($role.'.residents.households-by-purok', ['purok_id' => $purok->id]))
            ->assertOk()->assertExactJson([
                $household->only(['id', 'household_no', 'household_address']),
                $second->only(['id', 'household_no', 'household_address']),
            ]);
        $response = $this->getJson(route($role.'.residents.households-by-purok', ['purok_id' => $foreign->purok_id]));
        if ($role === 'secretary') {
            $response->assertForbidden();
        } else {
            $response->assertOk()->assertExactJson([$foreign->only(['id', 'household_no', 'household_address'])]);
        }
        foreach ([[], ['purok_id' => 'invalid'], ['purok_id' => 999999]] as $query) {
            $this->getJson(route($role.'.residents.households-by-purok', $query))
                ->assertRedirect()->assertSessionHasErrors('purok_id');
        }
        $this->assertFalse($inactive->is_active);
    }

    #[DataProvider('roles')]
    public function test_create_edit_preserve_preloads_old_input_and_use_the_same_guard(string $role): void
    {
        [$user, $purok, $household, $resident] = $this->fixture($role);
        $oldHousehold = $this->household($purok, '009');
        $old = ['barangay_id' => (string) $purok->barangay_id, 'purok_id' => (string) $purok->id,
            'household_id' => (string) $oldHousehold->id];
        $this->actingAs($user)->withSession(['_old_input' => $old]);
        foreach ([
            route($role.'.residents.create', ['household_id' => $household->id]),
            route($role.'.residents.edit', $resident),
        ] as $url) {
            $this->get($url)->assertOk()
                ->assertViewHas('availablePuroks', fn ($options) => $options->contains('id', $purok->id))
                ->assertViewHas('availableHouseholds', fn ($options) => $options->contains('id', $household->id))
                ->assertSee("'".$old['barangay_id']."'", false)
                ->assertSee("'".$old['purok_id']."'", false)
                ->assertSee("'".$old['household_id']."'", false)
                ->assertSee('this.loadPuroks(true)', false)
                ->assertSee('this.loadHouseholds(true)', false)
                ->assertSee('householdRequestGeneration: 0', false)
                ->assertSee('purokRequestGeneration: 0', false)
                ->assertSee('role="status"', false)
                ->assertSee('Unable to load households.')
                ->assertSee('@change="loadHouseholds()"', false)
                ->assertSee('rankingEnabled: '.($role === 'secretary' ? 'true' : 'false'), false);
        }
    }

    public function test_household_helpers_remain_unavailable_to_other_roles_and_guests(): void
    {
        $purok = Purok::factory()->create();
        foreach (['secretary', 'admin'] as $prefix) {
            $url = route($prefix.'.residents.households-by-purok', ['purok_id' => $purok->id]);
            $this->getJson($url)->assertRedirect(route('login'));
            foreach (['bhw', 'bns', 'phn', 'mho'] as $role) {
                $user = User::factory()->create(['role' => $role, 'assigned_barangay_id' => $purok->barangay_id]);
                $this->actingAs($user)->getJson($url)->assertForbidden();
            }
            auth()->forgetGuards();
        }
    }

    public static function roles(): array
    {
        return [['secretary'], ['admin']];
    }

    private function fixture(string $role): array
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id]);
        $household = $this->household($purok, '001');
        $resident = Resident::create(['household_id' => $household->id, 'first_name' => 'Synthetic',
            'last_name' => 'Resident', 'birth_date' => '1990-01-01', 'birth_place' => 'Tubigon',
            'sex' => 'Female', 'civil_status' => 'Single', 'citizenship' => 'Filipino',
            'relationship_to_head' => 'Other Relative', 'resident_status' => 'active', 'is_active' => true]);
        $user = User::factory()->create(['role' => $role, 'assigned_barangay_id' => $barangay->id]);

        return [$user, $purok, $household, $resident];
    }

    private function household(Purok $purok, string $number, bool $active = true): Household
    {
        return Household::create(['purok_id' => $purok->id, 'household_no' => $number,
            'household_address' => 'Synthetic address', 'is_active' => $active]);
    }
}
