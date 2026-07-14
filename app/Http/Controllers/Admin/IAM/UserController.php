<?php

namespace App\Http\Controllers\Admin\IAM;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IAM\UserStoreRequest;
use App\Http\Requests\Admin\IAM\UserUpdateRequest;
use App\Models\Barangay;
use App\Models\Purok;
use App\Models\User;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        $users = $this->filteredQuery($request)
                       ->with(['assignedBarangay', 'assignedPurok', 'requestedBarangay', 'requestedPurok'])
                       ->latest()
                       ->paginate(15)
                       ->withQueryString();

        $roles = User::ROLES;
        $barangays = Barangay::active()->get();
        $approvalStatuses = [
            User::APPROVAL_PENDING => 'Pending Approval',
            User::APPROVAL_APPROVED => 'Approved',
            User::APPROVAL_REJECTED => 'Rejected',
        ];
        $approvalQueueOptions = [
            'secretary' => 'Secretary Approval Queue',
            'municipal' => 'Municipal Admin Queue',
        ];
        $approvalQueues = $this->pendingApprovalQueues();

        return view('admin.iam.users.index', compact(
            'users',
            'roles',
            'barangays',
            'approvalStatuses',
            'approvalQueueOptions',
            'approvalQueues',
        ));
    }

    /**
     * Export users in CSV, XLSX, or PDF format.
     */
    public function export(Request $request, string $format)
    {
        Gate::authorize('viewAny', User::class);

        $users = $this->filteredQuery($request)
            ->with(['assignedBarangay', 'assignedPurok', 'requestedBarangay', 'requestedPurok'])
            ->latest()
            ->get();

        $columns = [
            'Name' => 'name',
            'Email' => 'email',
            'Role' => fn (User $user) => $user->role_label,
            'Approval Queue' => fn (User $user) => $user->approval_queue_label,
            'Assignment' => fn (User $user) => $user->assignment_label,
            'Approval' => fn (User $user) => $user->approval_status_label,
            'Registration Source' => fn (User $user) => $user->registered_via_label,
            'Status' => fn (User $user) => $user->is_active ? 'Active' : 'Inactive',
            'Joined' => fn (User $user) => $user->created_at?->format('Y-m-d H:i:s'),
            'Deleted At' => fn (User $user) => $user->deleted_at?->format('Y-m-d H:i:s') ?? 'N/A',
        ];

        $filters = [
            'Search' => $request->string('search')->toString(),
            'Role' => $request->filled('role') ? (User::ROLES[$request->role] ?? $request->role) : null,
            'Status' => $request->filled('status') ? ucfirst($request->status) : null,
            'Approval' => $request->filled('approval_status') ? ucfirst($request->approval_status) : null,
            'Approval Queue' => $request->filled('approval_queue')
                ? ($request->approval_queue === 'secretary' ? 'Secretary Approval Queue' : 'Municipal Admin Queue')
                : null,
            'Barangay' => Barangay::find($request->integer('barangay'))?->name,
            'Lifecycle' => $request->filled('lifecycle') ? ucfirst($request->lifecycle) : 'Current',
        ];

        $timestamp = now()->format('Y-m-d_His');

        ExportAudit::log('user registry', $format, [
            'model_type' => User::class,
            'record_count' => $users->count(),
            'filters' => array_filter($filters),
        ]);

        return match ($format) {
            'csv' => TabularExport::csv("users_{$timestamp}.csv", $columns, $users),
            'xlsx' => TabularExport::xlsx("users_{$timestamp}.xlsx", 'Users', $columns, $users),
            'pdf' => TabularExport::pdf("users_{$timestamp}.pdf", 'User Management Report', $columns, $users, $filters),
            default => abort(404),
        };
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        Gate::authorize('create', User::class);

        $roles = User::ROLES;
        $barangays = Barangay::active()->get();
        $puroks = collect();

        return view('admin.iam.users.create', compact('roles', 'barangays', 'puroks'));
    }

    /**
     * Store a newly created user.
     */
    public function store(UserStoreRequest $request)
    {
        Gate::authorize('create', User::class);

        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['approval_status'] = User::APPROVAL_APPROVED;
        $data['registered_via'] = 'admin';
        $data['is_active'] = $data['is_active'] ?? true;

        $user = User::create($data);

        // Log the creation
        \App\Models\AuditLog::logMutation('created', Auth::user(), $user);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User {$user->name} created successfully.");
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        Gate::authorize('view', $user);

        $user->load([
            'assignedBarangay',
            'assignedPurok',
            'requestedBarangay',
            'requestedPurok',
            'auditLogs' => fn ($query) => $query->latest()->limit(10),
        ]);

        return view('admin.iam.users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user)
    {
        Gate::authorize('update', $user);

        $roles = User::ROLES;
        $barangays = Barangay::active()->get();
        $puroks = $user->assigned_barangay_id 
            ? Purok::where('barangay_id', $user->assigned_barangay_id)->active()->get()
            : collect();

        return view('admin.iam.users.edit', compact('user', 'roles', 'barangays', 'puroks'));
    }

    /**
     * Update the specified user.
     */
    public function update(UserUpdateRequest $request, User $user)
    {
        Gate::authorize('update', $user);

        $oldValues = $user->toArray();
        $data = $request->validated();

        // Don't update password if not provided
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        // Log the update
        \App\Models\AuditLog::logMutation('updated', Auth::user(), $user, $oldValues, $user->toArray());

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User {$user->name} updated successfully.");
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user)
    {
        Gate::authorize('delete', $user);

        $oldValues = $user->toArray();
        $userName = $user->name;

        $user->delete();

        // Log the deletion
        \App\Models\AuditLog::logMutation('deleted', Auth::user(), $user, $oldValues);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User {$userName} deleted successfully.");
    }

    /**
     * Restore the specified user.
     */
    public function restore($id)
    {
        $user = User::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $user);

        $user->restore();

        \App\Models\AuditLog::logMutation('restored', Auth::user(), $user);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User {$user->name} restored successfully.");
    }

    /**
     * Get puroks for a specific barangay (AJAX).
     */
    public function getPuroks(Request $request)
    {
        Gate::authorize('viewAny', Purok::class);

        $barangayId = $request->barangay_id;

        if (!$barangayId) {
            return response()->json([]);
        }

        $puroks = Purok::where('barangay_id', $barangayId)
                       ->active()
                       ->orderBy('purok_number')
                       ->get(['id', 'purok_number', 'purok_name']);

        return response()->json($puroks);
    }

    /**
     * Build the filtered user query for listings and exports.
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = User::query();

        if ($request->input('lifecycle') === 'all') {
            $query->withTrashed();
        } elseif ($request->input('lifecycle') === 'deleted') {
            $query->onlyTrashed();
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }

        if ($request->filled('approval_queue')) {
            $targetRoles = $request->string('approval_queue')->toString() === 'secretary'
                ? User::SECRETARY_APPROVAL_ROLES
                : User::MUNICIPAL_APPROVAL_ROLES;

            $query->where('approval_status', User::APPROVAL_PENDING);
            $query->where(function (Builder $builder) use ($targetRoles): void {
                $builder->whereIn('requested_role', $targetRoles)
                    ->orWhere(function (Builder $fallbackBuilder) use ($targetRoles): void {
                        $fallbackBuilder->whereNull('requested_role')
                            ->whereIn('role', $targetRoles);
                    });
            });
        }

        if ($request->filled('barangay')) {
            $query->where(function (Builder $builder) use ($request): void {
                $builder->where('assigned_barangay_id', $request->barangay)
                    ->orWhere('requested_barangay_id', $request->barangay);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        return $query;
    }

    private function pendingApprovalQueues(): array
    {
        $pendingUsers = User::query()
            ->pendingApproval()
            ->with(['requestedBarangay', 'requestedPurok', 'assignedBarangay', 'assignedPurok'])
            ->latest()
            ->get();

        $secretaryQueue = $pendingUsers
            ->filter(fn (User $user) => $user->approval_queue === 'secretary')
            ->values();

        $municipalQueue = $pendingUsers
            ->filter(fn (User $user) => $user->approval_queue === 'municipal')
            ->values();

        return [
            'secretary' => [
                'label' => 'Secretary Approval Queue',
                'description' => 'BHW and BNS signups waiting for their assigned barangay secretary to review and place.',
                'count' => $secretaryQueue->count(),
                'oldest' => $secretaryQueue->last(),
                'users' => $secretaryQueue->take(5),
            ],
            'municipal' => [
                'label' => 'Municipal Admin Queue',
                'description' => 'Secretary, PHN, MHO, and any supervisory registrations that require direct municipal approval.',
                'count' => $municipalQueue->count(),
                'oldest' => $municipalQueue->last(),
                'users' => $municipalQueue->take(5),
            ],
        ];
    }
}
