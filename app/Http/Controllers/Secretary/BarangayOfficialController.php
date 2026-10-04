<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Secretary\Concerns\InteractsWithSecretaryScope;
use App\Models\Barangay;
use App\Models\Household;
use App\Support\BarangayOfficialsRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BarangayOfficialController extends Controller
{
    use InteractsWithSecretaryScope;

    public function index(BarangayOfficialsRegistry $registry): View
    {
        return $this->rosterView('secretary.officials.index', $registry);
    }

    public function edit(BarangayOfficialsRegistry $registry): View
    {
        return $this->rosterView('secretary.officials.edit', $registry);
    }

    private function rosterView(string $view, BarangayOfficialsRegistry $registry): View
    {
        Gate::authorize('viewAny', Household::class);

        $barangay = Barangay::query()->findOrFail($this->assignedBarangayId());

        return view($view, [
            'barangay' => $barangay,
            'officials' => $registry->syncDefaults($barangay),
            ...$registry->secretaryPresentation($barangay),
        ]);
    }
}
