<?php

namespace App\Http\Controllers\Bns;

use App\Http\Controllers\Bns\Concerns\InteractsWithBnsScope;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserPasswordController extends Controller
{
    use InteractsWithBnsScope;

    public function edit(User $user): View
    {
        $this->ensureBhwBelongsToBarangay($user);

        return view('bns.team.password', [
            'bhw' => $user,
        ]);
    }

    public function reset(Request $request, User $user): RedirectResponse
    {
        $this->ensureBhwBelongsToBarangay($user);

        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $oldValues = ['password_changed' => true];

        $user->update([
            'password' => Hash::make($request->input('password')),
        ]);

        AuditLog::logMutation('password_reset', Auth::user(), $user, $oldValues);

        return redirect()
            ->route('bns.team.show', $user)
            ->with('success', "Password for {$user->name} has been reset successfully.");
    }

    public function generateTemporary(User $user): RedirectResponse
    {
        $this->ensureBhwBelongsToBarangay($user);

        $temporaryPassword = Str::random(12);

        $user->update([
            'password' => Hash::make($temporaryPassword),
        ]);

        AuditLog::logMutation('password_reset', Auth::user(), $user, [
            'temporary_password_generated' => true,
        ]);

        return redirect()
            ->route('bns.team.password.edit', $user)
            ->with('success', "Temporary password generated for {$user->name}.")
            ->with('temporary_password', $temporaryPassword);
    }
}
