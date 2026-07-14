<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectController extends Controller
{
    /**
     * Redirect users based on their role after login.
     */
    public function redirect(Request $request)
    {
        $user = Auth::user();

        // Check if user is active
        if (!$user->is_active) {
            Auth::logout();
            return redirect()->route('login')
                ->with('error', 'Your account has been deactivated. Please contact the administrator.');
        }

        // Redirect based on role
        switch ($user->role) {
            case 'admin':
                return redirect()->route('admin.dashboard');
            case 'bns':
                return redirect()->route('bns.dashboard');
            case 'secretary':
                return redirect()->route('secretary.dashboard');
            case 'bhw':
                return redirect()->route('bhw.dashboard');

            default:
                return redirect()->route('dashboard');
        }
    }
}
