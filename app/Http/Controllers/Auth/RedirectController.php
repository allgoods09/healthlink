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
            case 'mho':
            case 'phn':
            case 'secretary':
            case 'bns':
                return redirect()->route('admin.dashboard');
            
            case 'bhw':
                // BHWs go to their mobile dashboard or a field worker page
                return redirect()->route('bhw.dashboard');
            
            default:
                // Fallback for any undefined roles
                return redirect()->route('home');
        }
    }
}