<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Secretary\Concerns\InteractsWithSecretaryScope;
use App\Http\Requests\Admin\Geometry\PurokStoreRequest;
use App\Http\Requests\Admin\Geometry\PurokUpdateRequest;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Purok;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PurokController extends Controller
{
    use InteractsWithSecretaryScope;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Purok::class);

        $puroks = $this->filteredQuery($request)
            ->withCount(['households', 'assignedUsers'])
            ->orderBy('purok_number')
            ->paginate(15)
            ->withQueryString();

        return view('admin.geometry.puroks.index', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'secretary',
            'pageTitle' => 'Purok Organization - HealthLink Secretary',
            'pageHeader' => 'Purok Organization',
            'canDelete' => false,
            'canRestore' => false,
            'puroks' => $puroks,
            'barangays' => Barangay::query()->whereKey($this->assignedBarangayId())->get(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        Gate::authorize('viewAny', Purok::class);

        $puroks = $this->filteredQuery($request)
            ->withCount(['households', 'assignedUsers'])
            ->orderBy('purok_number')
            ->get();

        $columns = [
            'Purok' => fn (Purok $purok) => $purok->display_name,
            'Barangay' => fn (Purok $purok) => $purok->barangay?->name,
            'Households' => 'households_count',
            'Assigned BHWs' => 'assigned_users_count',
            'Status' => fn (Purok $purok) => $purok->is_active ? 'Active' : 'Inactive',
        ];

        $filters = [
            'Search' => $request->string('search')->toString(),
            'Barangay' => $this->secretaryUser()->assignedBarangay?->name,
            'Status' => $request->filled('status') ? ucfirst($request->status) : null,
        ];

        ExportAudit::log('secretary purok registry', $format, [
            'model_type' => Purok::class,
            'record_count' => $puroks->count(),
            'filters' => array_filter($filters),
        ]);

        $timestamp = now()->format('Y-m-d_His');

        return match ($format) {
            'csv' => TabularExport::csv("secretary_puroks_{$timestamp}.csv", $columns, $puroks),
            'xlsx' => TabularExport::xlsx("secretary_puroks_{$timestamp}.xlsx", 'Secretary Puroks', $columns, $puroks),
            'pdf' => TabularExport::pdf("secretary_puroks_{$timestamp}.pdf", 'Barangay Purok Registry', $columns, $puroks, $filters),
            default => abort(404),
        };
    }

    public function create(): View
    {
        Gate::authorize('create', Purok::class);

        return view('admin.geometry.puroks.create', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'secretary',
            'pageTitle' => 'Create Purok - HealthLink Secretary',
            'pageHeader' => 'Create Purok',
            'barangays' => Barangay::query()->whereKey($this->assignedBarangayId())->get(),
        ]);
    }

    public function store(PurokStoreRequest $request): RedirectResponse
    {
        Gate::authorize('create', Purok::class);

        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        $purok = Purok::create($data);

        AuditLog::logMutation('created', Auth::user(), $purok);

        return redirect()
            ->route('secretary.puroks.index')
            ->with('success', "Purok {$purok->display_name} created successfully.");
    }

    public function show(Purok $purok): View
    {
        Gate::authorize('view', $purok);
        $this->ensurePurokBelongsToBarangay($purok);

        $purok->load(['barangay', 'households.residents', 'assignedUsers']);

        return view('admin.geometry.puroks.show', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'secretary',
            'pageTitle' => 'Purok Details - HealthLink Secretary',
            'pageHeader' => 'Purok Details',
            'purok' => $purok,
            'totalHouseholds' => $purok->households->count(),
            'totalResidents' => $purok->households->flatMap(fn ($household) => $household->residents)->count(),
            'bhws' => $purok->assignedUsers->where('role', 'bhw')->values(),
        ]);
    }

    public function edit(Purok $purok): View
    {
        Gate::authorize('update', $purok);
        $this->ensurePurokBelongsToBarangay($purok);

        return view('admin.geometry.puroks.edit', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'secretary',
            'pageTitle' => 'Edit Purok - HealthLink Secretary',
            'pageHeader' => 'Edit Purok',
            'purok' => $purok,
            'barangays' => Barangay::query()->whereKey($this->assignedBarangayId())->get(),
        ]);
    }

    public function update(PurokUpdateRequest $request, Purok $purok): RedirectResponse
    {
        Gate::authorize('update', $purok);
        $this->ensurePurokBelongsToBarangay($purok);

        $oldValues = $purok->toArray();
        $purok->update($request->validated());

        AuditLog::logMutation('updated', Auth::user(), $purok, $oldValues, $purok->toArray());

        return redirect()
            ->route('secretary.puroks.index')
            ->with('success', "Purok {$purok->display_name} updated successfully.");
    }

    public function toggleStatus(Purok $purok): RedirectResponse
    {
        Gate::authorize('toggleStatus', $purok);
        $this->ensurePurokBelongsToBarangay($purok);

        $oldStatus = $purok->is_active;
        $newStatus = ! $oldStatus;
        $purok->update(['is_active' => $newStatus]);

        AuditLog::logMutation('status_toggled', Auth::user(), $purok, [
            'is_active' => $oldStatus,
        ], [
            'is_active' => $newStatus,
        ]);

        return back()->with('success', "Purok {$purok->display_name} has been ".($newStatus ? 'activated' : 'marked inactive').'.');
    }

    public function getByBarangay(Request $request)
    {
        $request->validate([
            'barangay_id' => ['required', 'integer'],
        ]);

        if ((int) $request->integer('barangay_id') !== $this->assignedBarangayId()) {
            return response()->json([]);
        }

        return response()->json(
            $this->secretaryPuroksQuery()
                ->active()
                ->orderBy('purok_number')
                ->get(['id', 'purok_number', 'purok_name'])
        );
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = $this->secretaryPuroksQuery();

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('purok_number', $search)
                    ->orWhere('purok_name', 'like', "%{$search}%");
            });
        }

        return $query;
    }
}
