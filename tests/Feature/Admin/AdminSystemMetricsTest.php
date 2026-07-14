<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSystemMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_system_metrics_page_and_apis_render_with_driver_aware_fallbacks(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.metrics.index'))
            ->assertOk()
            ->assertSee('System Health & Metrics')
            ->assertSee('Database')
            ->assertSee('Storage');

        $this->actingAs($admin)
            ->get(route('admin.metrics.query-performance'))
            ->assertOk()
            ->assertJsonStructure([
                'driver',
                'connection_name',
                'connection_status',
                'supports_slow_query_logs',
                'slow_queries',
                'meta',
                'notes',
            ]);

        $this->actingAs($admin)
            ->get(route('api.admin.health'))
            ->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'checks' => [
                    'database' => ['healthy', 'details'],
                    'storage' => ['healthy', 'details'],
                    'session' => ['healthy', 'details'],
                    'cache' => ['healthy', 'details'],
                    'queue' => ['healthy', 'details'],
                ],
                'timestamp',
            ]);
    }
}
