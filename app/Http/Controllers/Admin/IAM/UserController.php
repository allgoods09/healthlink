<?php

namespace App\Http\Controllers\Admin\IAM;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IAM\UserStoreRequest;
use App\Http\Requests\Admin\IAM\UserUpdateRequest;
use App\Models\Barangay;
use App\Models\Purok;
use App\Models\User;
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

        $query = User::query();

        // Filter by role
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Filter by barangay
        if ($request->filled('barangay')) {
            $query->where('assigned_barangay_id', $request->barangay);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $users = $query->with(['assignedBarangay', 'assignedPurok'])
                       ->latest()
                       ->paginate(15)
                       ->withQueryString();

        $roles = User::ROLES;
        $barangays = Barangay::active()->get();

        return view('admin.iam.users.index', compact('users', 'roles', 'barangays'));
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

        $user->load(['assignedBarangay', 'assignedPurok']);

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
}