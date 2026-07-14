<?php

namespace App\Http\Controllers\Admin\IAM;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class UserPasswordController extends Controller
{
    /**
     * Show the password reset form.
     */
    public function edit(User $user)
    {
        Gate::authorize('resetPassword', $user);

        return view('admin.iam.users.password', compact('user'));
    }

    /**
     * Reset user password to a temporary one.
     */
    public function reset(Request $request, User $user)
    {
        Gate::authorize('resetPassword', $user);

        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $oldPassword = $user->password;
        $newPassword = Hash::make($request->password);

        $user->update(['password' => $newPassword]);

        // Log the password reset
        \App\Models\AuditLog::logMutation('password_reset', Auth::user(), $user, [
            'password_changed' => true
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Password for {$user->name} has been reset successfully.");
    }

    /**
     * Generate a temporary random password for manual admin handoff.
     */
    public function generateTemporary(User $user)
    {
        Gate::authorize('resetPassword', $user);

        $temporaryPassword = Str::random(12);
        $hashedPassword = Hash::make($temporaryPassword);

        $user->update(['password' => $hashedPassword]);

        // Log the password generation
        \App\Models\AuditLog::logMutation('password_reset', Auth::user(), $user, [
            'temporary_password_generated' => true
        ]);

        return redirect()
            ->back()
            ->with('success', "Temporary password generated for {$user->name}. Copy it now and share it securely.")
            ->with('temporary_password', $temporaryPassword);
    }
}
