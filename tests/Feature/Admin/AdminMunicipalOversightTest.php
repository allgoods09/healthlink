<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMunicipalOversightTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_command_center_and_oversight_monitors_render(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Municipal Command Center');

        $this->actingAs($admin)
            ->get(route('admin.oversight.field'))
            ->assertOk()
            ->assertSee('Field Operations Monitor');

        $this->actingAs($admin)
            ->get(route('admin.oversight.nutrition'))
            ->assertOk()
            ->assertSee('Nutrition Oversight');

        $this->actingAs($admin)
            ->get(route('admin.oversight.clinical'))
            ->assertOk()
            ->assertSee('Clinical Oversight');
    }
}
