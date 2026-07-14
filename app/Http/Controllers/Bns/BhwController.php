<?php

namespace App\Http\Controllers\Bns;

use App\Http\Controllers\Bns\Concerns\InteractsWithBnsScope;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FieldVisit;
use App\Models\Purok;
use App\Models\SyncLog;
use App\Models\User;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class BhwController extends Controller
{
    use InteractsWithBnsScope;

    public function create(): View
    {
        return view('bns.team.create', [
            'puroks' => $this->bnsPuroksQuery()->active()->orderBy('purok_number')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'assigned_purok_id' => [
                'required',
                'integer',
                'exists:puroks,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->bnsPuroksQuery()->active()->whereKey($value)->exists()) {
                        $fail('The selected purok is not available in your assigned barangay.');
                    }
                },
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $purok = $this->bnsPuroksQuery()->active()->find($validated['assigned_purok_id']);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'bhw',
            'approval_status' => User::APPROVAL_APPROVED,
            'registered_via' => 'bns',
            'requested_role' => null,
            'requested_barangay_id' => null,
            'requested_purok_id' => null,
            'assigned_barangay_id' => $this->assignedBarangayId(),
            'assigned_purok_id' => $purok->id,
            'approval_notes' => null,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
            'rejected_at' => null,
            'rejected_by' => null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLog::logMutation('created', Auth::user(), $user);

        return redirect()
            ->route('bns.team.show', $user)
            ->with('success', "BHW account for {$user->name} has been created.");
    }

    public function index(Request $request): View
    {
        $bhws = $this->filteredQuery($request)
            ->with(['assignedBarangay', 'assignedPurok', 'requestedBarangay', 'requestedPurok'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('bns.team.index', [
            'bhws' => $bhws,
            'puroks' => $this->bnsPuroksQuery()->active()->orderBy('purok_number')->get(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $bhws = $this->filteredQuery($request)
            ->with(['assignedBarangay', 'assignedPurok', 'requestedBarangay', 'requestedPurok'])
            ->latest()
            ->get();

        $columns = [
            'Name' => 'name',
            'Email' => 'email',
            'Assigned Purok' => fn (User $user) => $user->assignedPurok?->display_name ?? 'Unassigned',
            'Requested Purok' => fn (User $user) => $user->requestedPurok?->display_name ?? 'N/A',
            'Approval Status' => fn (User $user) => $user->approval_status_label,
            'Status' => fn (User $user) => $user->is_active ? 'Active' : 'Inactive',
            'Registered Via' => fn (User $user) => $user->registered_via_label,
            'Joined' => fn (User $user) => $user->created_at?->format('Y-m-d H:i:s'),
        ];

        $filters = [
            'Search' => $request->string('search')->toString(),
            'Purok' => Purok::find($request->integer('purok_id'))?->display_name,
            'Approval' => $request->filled('approval_status') ? ucfirst($request->approval_status) : null,
            'Status' => $request->filled('status') ? ucfirst($request->status) : null,
        ];

        ExportAudit::log('bns bhw roster', $format, [
            'model_type' => User::class,
            'record_count' => $bhws->count(),
            'filters' => array_filter($filters),
        ]);

        $timestamp = now()->format('Y-m-d_His');

        return match ($format) {
            'csv' => TabularExport::csv("bns_bhw_roster_{$timestamp}.csv", $columns, $bhws),
            'xlsx' => TabularExport::xlsx("bns_bhw_roster_{$timestamp}.xlsx", 'BHW Roster', $columns, $bhws),
            'pdf' => TabularExport::pdf("bns_bhw_roster_{$timestamp}.pdf", 'Barangay Health Worker Roster', $columns, $bhws, $filters),
            default => abort(404),
        };
    }

    public function show(User $user): View
    {
        $this->ensureBhwBelongsToBarangay($user);

        $user->load(['assignedBarangay', 'assignedPurok', 'requestedBarangay', 'requestedPurok']);

        return view('bns.team.show', [
            'bhw' => $user,
            'tokenCount' => PersonalAccessToken::query()
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $user->id)
                ->count(),
            'recentSyncs' => SyncLog::query()
                ->where('user_id', $user->id)
                ->latest()
                ->limit(5)
                ->get(),
            'recentVisits' => FieldVisit::query()
                ->with('household.purok')
                ->where('recorded_by_user_id', $user->id)
                ->latest('visited_at')
                ->limit(5)
                ->get(),
            'recentActivity' => AuditLog::query()
                ->with('user')
                ->where('model_type', User::class)
                ->where('model_id', $user->id)
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }

    public function edit(User $user): View
    {
        $this->ensureBhwBelongsToBarangay($user);

        return view('bns.team.edit', [
            'bhw' => $user->load(['assignedBarangay', 'assignedPurok', 'requestedPurok']),
            'puroks' => $this->bnsPuroksQuery()->active()->orderBy('purok_number')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureBhwBelongsToBarangay($user);

        $validated = $request->validate([
            'assigned_purok_id' => ['required', 'exists:puroks,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $purok = $this->bnsPuroksQuery()->findOrFail($validated['assigned_purok_id']);
        $oldValues = $user->toArray();

        $user->forceFill([
            'role' => 'bhw',
            'assigned_barangay_id' => $this->assignedBarangayId(),
            'assigned_purok_id' => $purok->id,
            'is_active' => $request->boolean('is_active'),
        ])->save();

        AuditLog::logMutation('updated', Auth::user(), $user, $oldValues, $user->fresh()->toArray());

        return redirect()
            ->route('bns.team.show', $user)
            ->with('success', "Assignment for {$user->name} has been updated.");
    }

    public function approve(User $user): RedirectResponse
    {
        $this->ensureBhwBelongsToBarangay($user);

        if ($user->approval_status === User::APPROVAL_APPROVED) {
            return back()->with('error', 'This BHW has already been approved.');
        }

        $assignedPurokId = $user->assigned_purok_id ?? $user->requested_purok_id;

        if (! $assignedPurokId || ! $this->bnsPuroksQuery()->whereKey($assignedPurokId)->exists()) {
            return back()->with('error', 'Assign this BHW to a valid purok in your barangay before approval.');
        }

        $oldValues = $user->toArray();

        $user->forceFill([
            'role' => 'bhw',
            'assigned_barangay_id' => $this->assignedBarangayId(),
            'assigned_purok_id' => $assignedPurokId,
            'is_active' => true,
            'approval_status' => User::APPROVAL_APPROVED,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
            'rejected_at' => null,
            'rejected_by' => null,
            'approval_notes' => null,
        ])->save();

        AuditLog::logMutation('updated', Auth::user(), $user, $oldValues, $user->fresh()->toArray());

        return back()->with('success', "Registration for {$user->name} has been approved.");
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        $this->ensureBhwBelongsToBarangay($user);

        if ($user->approval_status !== User::APPROVAL_PENDING) {
            return back()->with('error', 'Only pending BHW registrations can be rejected.');
        }

        $validated = $request->validate([
            'approval_notes' => ['required', 'string', 'max:500'],
        ]);

        $oldValues = $user->toArray();

        $user->forceFill([
            'is_active' => false,
            'approval_status' => User::APPROVAL_REJECTED,
            'approved_at' => null,
            'approved_by' => null,
            'rejected_at' => now(),
            'rejected_by' => Auth::id(),
            'approval_notes' => $validated['approval_notes'],
        ])->save();

        AuditLog::logMutation('updated', Auth::user(), $user, $oldValues, $user->fresh()->toArray());

        return back()->with('success', "Registration for {$user->name} has been rejected.");
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = $this->bnsBhwsQuery();

        if ($request->filled('purok_id')) {
            $query->where(function (Builder $builder) use ($request): void {
                $builder->where('assigned_purok_id', $request->integer('purok_id'))
                    ->orWhere('requested_purok_id', $request->integer('purok_id'));
            });
        }

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->input('approval_status'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query;
    }
}
