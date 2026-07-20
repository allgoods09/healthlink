<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Secretary\Concerns\InteractsWithSecretaryScope;
use App\Models\AuditLog;
use App\Models\Purok;
use App\Models\User;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class FrontlineUserController extends Controller
{
    use InteractsWithSecretaryScope;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $users = $this->filteredQuery($request)
            ->with(['assignedBarangay', 'assignedPurok', 'requestedBarangay'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('secretary.team.index', [
            'users' => $users,
            'puroks' => $this->secretaryPuroksQuery()->active()->orderBy('purok_number')->get(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        Gate::authorize('viewAny', User::class);

        $users = $this->filteredQuery($request)
            ->with(['assignedBarangay', 'assignedPurok', 'requestedBarangay'])
            ->latest()
            ->get();

        $columns = [
            'Name' => 'name',
            'Email' => 'email',
            'Role' => fn (User $user) => $user->role_label,
            'Assigned Barangay' => fn (User $user) => $user->assignedBarangay?->name ?: $user->requestedBarangay?->name,
            'Assigned Purok' => fn (User $user) => $user->assignedPurok?->display_name ?: 'N/A',
            'Approval Status' => fn (User $user) => $user->approval_status_label,
            'Status' => fn (User $user) => $user->is_active ? 'Active' : 'Inactive',
            'Registered Via' => fn (User $user) => $user->registered_via_label,
            'Joined' => fn (User $user) => optional($user->created_at)?->format('Y-m-d H:i:s'),
        ];

        $filters = [
            'Search' => $request->string('search')->toString(),
            'Role' => $request->filled('role') ? strtoupper($request->string('role')->toString()) : null,
            'Purok' => Purok::find($request->integer('purok_id'))?->display_name,
            'Approval' => $request->filled('approval_status') ? ucfirst($request->string('approval_status')->toString()) : null,
            'Status' => $request->filled('status') ? ucfirst($request->string('status')->toString()) : null,
        ];

        ExportAudit::log('secretary frontline roster', $format, [
            'model_type' => User::class,
            'record_count' => $users->count(),
            'filters' => array_filter($filters),
        ]);

        $timestamp = now()->format('Y-m-d_His');

        return match ($format) {
            'csv' => TabularExport::csv("secretary_frontline_users_{$timestamp}.csv", $columns, $users),
            'xlsx' => TabularExport::xlsx("secretary_frontline_users_{$timestamp}.xlsx", 'Frontline Users', $columns, $users),
            'pdf' => TabularExport::pdf("secretary_frontline_users_{$timestamp}.pdf", 'Frontline User Roster', $columns, $users, $filters),
            default => abort(404),
        };
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('secretary.team.create', [
            'puroks' => $this->secretaryPuroksQuery()->active()->orderBy('purok_number')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $validated = $request->validate([
            'role' => ['required', 'in:bhw,bns'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'assigned_purok_id' => [
                'nullable',
                'integer',
                'exists:puroks,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value && ! $this->secretaryPuroksQuery()->active()->whereKey($value)->exists()) {
                        $fail('The selected purok is not available in your assigned barangay.');
                    }
                },
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validated['role'] === 'bhw' && empty($validated['assigned_purok_id'])) {
            return back()->withErrors([
                'assigned_purok_id' => 'Please assign a purok when creating a BHW account.',
            ])->withInput();
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'requested_role' => null,
            'approval_status' => User::APPROVAL_APPROVED,
            'registered_via' => 'secretary',
            'assigned_barangay_id' => $this->assignedBarangayId(),
            'assigned_purok_id' => $validated['role'] === 'bhw' ? $validated['assigned_purok_id'] : null,
            'requested_barangay_id' => null,
            'requested_purok_id' => null,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
            'email_verified_at' => now(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLog::logMutation('created', Auth::user(), $user);

        return redirect()
            ->route('secretary.team.show', $user)
            ->with('success', "{$user->role_label} account for {$user->name} has been created.");
    }

    public function show(User $user): View
    {
        Gate::authorize('view', $user);
        $this->ensureFrontlineUserBelongsToBarangay($user);

        $user->load(['assignedBarangay', 'assignedPurok', 'requestedBarangay']);

        return view('secretary.team.show', [
            'frontlineUser' => $user,
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
        Gate::authorize('update', $user);
        $this->ensureFrontlineUserBelongsToBarangay($user);

        return view('secretary.team.edit', [
            'frontlineUser' => $user->load(['assignedBarangay', 'assignedPurok', 'requestedBarangay']),
            'puroks' => $this->secretaryPuroksQuery()->active()->orderBy('purok_number')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        $this->ensureFrontlineUserBelongsToBarangay($user);

        $validated = $request->validate([
            'role' => ['required', 'in:bhw,bns'],
            'assigned_purok_id' => [
                'nullable',
                'exists:puroks,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value && ! $this->secretaryPuroksQuery()->active()->whereKey($value)->exists()) {
                        $fail('The selected purok is not available in your assigned barangay.');
                    }
                },
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validated['role'] === 'bhw' && empty($validated['assigned_purok_id'])) {
            return back()->withErrors([
                'assigned_purok_id' => 'Please assign a purok for BHW accounts.',
            ])->withInput();
        }

        $oldValues = $user->toArray();

        $user->forceFill([
            'role' => $validated['role'],
            'requested_role' => $user->approval_status === User::APPROVAL_PENDING ? $validated['role'] : null,
            'assigned_barangay_id' => $this->assignedBarangayId(),
            'requested_barangay_id' => $user->approval_status === User::APPROVAL_PENDING ? $this->assignedBarangayId() : null,
            'assigned_purok_id' => $validated['role'] === 'bhw' ? $validated['assigned_purok_id'] : null,
            'requested_purok_id' => $user->approval_status === User::APPROVAL_PENDING && $validated['role'] === 'bhw'
                ? $validated['assigned_purok_id']
                : null,
            'is_active' => $request->boolean('is_active'),
        ])->save();

        AuditLog::logMutation('updated', Auth::user(), $user, $oldValues, $user->fresh()->toArray());

        return redirect()
            ->route('secretary.team.show', $user)
            ->with('success', "Assignment for {$user->name} has been updated.");
    }

    public function approve(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        $this->ensureFrontlineUserBelongsToBarangay($user);

        if ($user->approval_status === User::APPROVAL_APPROVED) {
            return back()->with('error', 'This user has already been approved.');
        }

        $role = $user->requested_role ?? $user->role;

        if ($role === 'bhw' && ! $user->assigned_purok_id) {
            return back()->with('error', 'Assign a valid purok before approving this BHW registration.');
        }

        $oldValues = $user->toArray();

        $user->forceFill([
            'role' => $role,
            'assigned_barangay_id' => $this->assignedBarangayId(),
            'approval_status' => User::APPROVAL_APPROVED,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
            'rejected_at' => null,
            'rejected_by' => null,
            'approval_notes' => null,
            'is_active' => true,
        ])->save();

        AuditLog::logMutation('updated', Auth::user(), $user, $oldValues, $user->fresh()->toArray());

        return back()->with('success', "Registration for {$user->name} has been approved.");
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        $this->ensureFrontlineUserBelongsToBarangay($user);

        if ($user->approval_status !== User::APPROVAL_PENDING) {
            return back()->with('error', 'Only pending self-registrations can be rejected.');
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

    public function resendVerification(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        $this->ensureFrontlineUserBelongsToBarangay($user);

        if ($user->hasVerifiedEmail()) {
            return back()->with('success', "{$user->name} already has a verified email address.");
        }

        $user->sendEmailVerificationNotification();

        AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'updated',
            'event_description' => "Verification email re-sent to {$user->email}",
            'model_type' => User::class,
            'model_id' => $user->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => [
                'action' => 'resend_verification_email',
            ],
        ]);

        return back()->with('success', "A fresh verification email was sent to {$user->email}.");
    }

    public function markVerified(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        $this->ensureFrontlineUserBelongsToBarangay($user);

        if ($user->hasVerifiedEmail()) {
            return back()->with('success', "{$user->name} already has a verified email address.");
        }

        $oldValues = $user->toArray();

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        AuditLog::logMutation('updated', Auth::user(), $user, $oldValues, $user->fresh()->toArray());

        return back()->with('success', "{$user->name}'s email has been marked as verified.");
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = $this->secretaryFrontlineUsersQuery();

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('purok_id')) {
            $query->where('assigned_purok_id', $request->input('purok_id'));
        }

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->input('approval_status'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    private function ensureFrontlineUserBelongsToBarangay(User $user): void
    {
        if (! in_array($user->role, ['bhw', 'bns'])) {
            abort(404);
        }

        if (
            (int) $user->assigned_barangay_id !== $this->assignedBarangayId()
            && (int) $user->requested_barangay_id !== $this->assignedBarangayId()
        ) {
            abort(404);
        }
    }
}
