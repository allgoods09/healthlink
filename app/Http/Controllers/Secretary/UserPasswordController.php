<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Secretary\Concerns\InteractsWithSecretaryScope;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserPasswordController extends Controller
{
    use InteractsWithSecretaryScope;

    public function edit(User $user): View
    {
        Gate::authorize('resetPassword', $user);
        $this->ensureFrontlineUserBelongsToBarangay($user);

        return view('secretary.team.password', [
            'frontlineUser' => $user,
        ]);
    }

    public function reset(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('resetPassword', $user);
        $this->ensureFrontlineUserBelongsToBarangay($user);

        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($request->input('password')),
        ]);

        AuditLog::logMutation('password_reset', Auth::user(), $user, [
            'password_changed' => true,
        ]);

        return redirect()
            ->route('secretary.team.show', $user)
            ->with('success', "Password for {$user->name} has been reset successfully.");
    }

    public function generateTemporary(User $user): RedirectResponse
    {
        Gate::authorize('resetPassword', $user);
        $this->ensureFrontlineUserBelongsToBarangay($user);

        $temporaryPassword = Str::random(12);

        $user->update([
            'password' => Hash::make($temporaryPassword),
        ]);

        AuditLog::logMutation('password_reset', Auth::user(), $user, [
            'temporary_password_generated' => true,
        ]);

        return redirect()
            ->route('secretary.team.password.edit', $user)
            ->with('success', "Temporary password generated for {$user->name}.")
            ->with('temporary_password', $temporaryPassword);
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
