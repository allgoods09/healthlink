<?php

namespace App\Http\Controllers\Admin\IAM;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

class UserStatusController extends Controller
{
    /**
     * Toggle user active status.
     */
    public function toggle(Request $request, User $user)
    {
        Gate::authorize('toggleStatus', $user);

        // Prevent users from deactivating themselves
        if ($user->id === Auth::id()) {
            return redirect()
                ->back()
                ->with('error', 'You cannot deactivate your own account.');
        }

        $oldStatus = $user->is_active;
        $newStatus = !$oldStatus;

        $user->update(['is_active' => $newStatus]);

        // Log the status change
        \App\Models\AuditLog::logMutation('status_toggled', Auth::user(), $user, [
            'is_active' => $oldStatus
        ], [
            'is_active' => $newStatus
        ]);

        $status = $newStatus ? 'activated' : 'deactivated';

        return redirect()
            ->back()
            ->with('success', "User {$user->name} has been {$status}.");
    }

    /**
     * Bulk toggle user statuses.
     */
    public function bulkToggle(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['exists:users,id'],
            'action' => ['required', 'in:activate,deactivate'],
        ]);

        $userIds = $request->user_ids;
        $action = $request->action;
        $status = $action === 'activate';

        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            // Skip if trying to deactivate self
            if ($action === 'deactivate' && $user->id === Auth::id()) {
                continue;
            }

            $oldStatus = $user->is_active;
            $user->update(['is_active' => $status]);

            \App\Models\AuditLog::logMutation('status_toggled', Auth::user(), $user, [
                'is_active' => $oldStatus
            ], [
                'is_active' => $status
            ]);
        }

        $message = count($users) . ' users have been ' . ($status ? 'activated' : 'deactivated') . '.';

        return redirect()
            ->back()
            ->with('success', $message);
    }
}