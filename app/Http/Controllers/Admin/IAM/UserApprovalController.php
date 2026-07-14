<?php

namespace App\Http\Controllers\Admin\IAM;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class UserApprovalController extends Controller
{
    /**
     * Approve a pending self-registration.
     */
    public function approve(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        if ($user->approval_status === User::APPROVAL_APPROVED) {
            return back()->with('error', 'This user has already been approved.');
        }

        $user->fill([
            'role' => $user->requested_role ?? $user->role,
            'assigned_barangay_id' => $user->assigned_barangay_id ?? $user->requested_barangay_id,
            'assigned_purok_id' => $user->assigned_purok_id ?? $user->requested_purok_id,
        ]);

        if ($user->role === 'bhw' && (! $user->assigned_barangay_id || ! $user->assigned_purok_id)) {
            return back()->with('error', 'Assign a barangay and purok before approving this BHW registration.');
        }

        $oldValues = $user->toArray();

        $user->forceFill([
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

    /**
     * Reject a pending self-registration.
     */
    public function reject(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

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
}
