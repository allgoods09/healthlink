<?php

namespace Tests\Feature\Secretary;

use App\Http\Controllers\Secretary\PurokController;
use App\Models\Barangay;
use App\Models\Purok;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PurokHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_helper_url_resolves_to_the_existing_helper_action_and_middleware(): void
    {
        $route = app('router')->getRoutes()->match(Request::create(
            route('secretary.puroks.get-by-barangay', ['barangay_id' => 1]), 'GET'
        ));

        $this->assertSame('secretary.puroks.get-by-barangay', $route->getName());
        $this->assertSame(PurokController::class.'@getByBarangay', $route->getActionName());
        $this->assertSame([], $route->wheres);
        $this->assertSame(['web', 'auth', 'verified', 'active', 'role:secretary', 'no-cache'], $route->gatherMiddleware());
    }

    public function test_named_helper_returns_assigned_puroks_with_existing_fields_and_order(): void
    {
        $secretary = $this->secretary();
        $second = Purok::factory()->create(['barangay_id' => $secretary->assigned_barangay_id, 'purok_number' => 2]);
        $first = Purok::factory()->create(['barangay_id' => $secretary->assigned_barangay_id, 'purok_number' => 1]);
        Purok::factory()->create();

        $this->actingAs($secretary)->getJson($this->helperUrl($secretary))->assertOk()->assertExactJson([
            ['id' => $first->id, 'purok_number' => $first->purok_number, 'purok_name' => $first->purok_name],
            ['id' => $second->id, 'purok_number' => $second->purok_number, 'purok_name' => $second->purok_name],
        ]);
    }

    public function test_foreign_barangay_returns_an_empty_array(): void
    {
        $secretary = $this->secretary();
        $foreign = Purok::factory()->create();

        $this->actingAs($secretary)->getJson(route('secretary.puroks.get-by-barangay', [
            'barangay_id' => $foreign->barangay_id,
        ]))->assertOk()->assertExactJson([]);
    }

    public function test_inactive_and_soft_deleted_puroks_are_excluded(): void
    {
        $secretary = $this->secretary();
        Purok::factory()->create(['barangay_id' => $secretary->assigned_barangay_id, 'purok_number' => 1, 'is_active' => false]);
        $deleted = Purok::factory()->create(['barangay_id' => $secretary->assigned_barangay_id, 'purok_number' => 2]);
        $deleted->delete();

        $this->actingAs($secretary)->getJson($this->helperUrl($secretary))->assertOk()->assertExactJson([]);
    }

    public function test_barangay_without_puroks_returns_an_empty_array(): void
    {
        $secretary = $this->secretary();

        $this->actingAs($secretary)->getJson($this->helperUrl($secretary))->assertOk()->assertExactJson([]);
    }

    public function test_missing_and_invalid_barangay_ids_keep_web_validation_errors(): void
    {
        $this->actingAs($this->secretary());

        foreach ([[], ['barangay_id' => 'invalid'], ['barangay_id' => '1.5']] as $query) {
            $this->getJson(route('secretary.puroks.get-by-barangay', $query))
                ->assertRedirect()->assertSessionHasErrors('barangay_id');
        }
    }

    public function test_numeric_detail_route_retains_implicit_binding_and_barangay_scope(): void
    {
        $secretary = $this->secretary();
        $purok = Purok::factory()->create(['barangay_id' => $secretary->assigned_barangay_id]);
        $route = app('router')->getRoutes()->match(Request::create('/secretary/puroks/1', 'GET'));
        $this->assertSame('secretary.puroks.show', $route->getName());
        $this->assertSame([], $route->wheres);

        $this->actingAs($secretary)->get(route('secretary.puroks.show', $purok))
            ->assertOk()->assertViewHas('purok', fn (Purok $bound) => $bound->is($purok));
        $this->get(route('secretary.puroks.show', Purok::factory()->create()))->assertForbidden();
    }

    public function test_guest_cannot_access_the_helper(): void
    {
        $this->getJson(route('secretary.puroks.get-by-barangay', ['barangay_id' => 1]))->assertRedirect(route('login'));
    }

    public function test_non_secretary_roles_cannot_access_the_helper(): void
    {
        $barangay = Barangay::factory()->create();

        foreach (['admin', 'bhw', 'bns', 'phn', 'mho'] as $role) {
            $user = User::factory()->create(['role' => $role, 'assigned_barangay_id' => $barangay->id]);
            $this->actingAs($user)->getJson($this->helperUrl($user))->assertForbidden();
        }
    }

    public function test_inactive_approved_secretary_is_denied_and_logged_out(): void
    {
        $secretary = $this->secretary(['is_active' => false]);

        $this->actingAs($secretary)->getJson($this->helperUrl($secretary))->assertForbidden();
        $this->assertGuest();
    }

    public function test_inactive_pending_secretary_keeps_the_pending_registration_redirect(): void
    {
        $secretary = $this->secretary(['is_active' => false, 'approval_status' => User::APPROVAL_PENDING]);

        $this->actingAs($secretary)->get($this->helperUrl($secretary))->assertRedirect(route('registration.pending'));
    }

    private function secretary(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'secretary',
            'assigned_barangay_id' => Barangay::factory()->create()->id,
        ], $attributes));
    }

    private function helperUrl(User $user): string
    {
        return route('secretary.puroks.get-by-barangay', ['barangay_id' => $user->assigned_barangay_id]);
    }
}
