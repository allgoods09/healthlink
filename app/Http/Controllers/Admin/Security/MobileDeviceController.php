<?php

namespace App\Http\Controllers\Admin\Devices;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Auth;

class MobileDeviceController extends Controller
{
    /**
     * Display a listing of mobile devices.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        // Get all BHWs with their tokens
        $query = User::where('role', 'bhw')
                     ->with(['assignedBarangay', 'assignedPurok']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $bhws = $query->get();

        // Get devices (tokens) for each BHW
        $devices = [];
        foreach ($bhws as $bhw) {
            $tokens = PersonalAccessToken::where('tokenable_id', $bhw->id)
                                         ->where('tokenable_type', User::class)
                                         ->latest()
                                         ->get();

            foreach ($tokens as $token) {
                $devices[] = [
                    'user' => $bhw,
                    'token' => $token,
                    'device_name' => $token->name ?? 'Unknown Device',
                    'last_used' => $token->last_used_at,
                    'created_at' => $token->created_at,
                ];
            }
        }

        return view('admin.devices.mobile.index', compact('devices'));
    }

    /**
     * Revoke a mobile device's API token.
     */
    public function revoke(Request $request, $tokenId)
    {
        Gate::authorize('viewAny', User::class);

        $token = PersonalAccessToken::findOrFail($tokenId);
        $user = User::find($token->tokenable_id);

        if (!$user) {
            return redirect()
                ->back()
                ->with('error', 'User associated with this token not found.');
        }

        $deviceName = $token->name ?? 'Unknown Device';
        $userName = $user->name;

        // Delete the token
        $token->delete();

        // Log the revocation
        \App\Models\AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'token_revoked',
            'event_description' => "Revoked mobile device '{$deviceName}' for user {$userName}",
            'model_type' => User::class,
            'model_id' => $user->id,
            'metadata' => [
                'device_name' => $deviceName,
                'revoked_by' => Auth::user()->name,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()
            ->route('admin.devices.index')
            ->with('success', "Device '{$deviceName}' for {$userName} has been revoked.");
    }

    /**
     * Revoke all devices for a specific user.
     */
    public function revokeAll(User $user)
    {
        Gate::authorize('viewAny', User::class);

        // Only BHWs should have mobile tokens
        if ($user->role !== 'bhw') {
            return redirect()
                ->back()
                ->with('error', 'This user is not a BHW and has no mobile devices.');
        }

        $tokenCount = PersonalAccessToken::where('tokenable_id', $user->id)
                                         ->where('tokenable_type', User::class)
                                         ->count();

        // Delete all tokens
        PersonalAccessToken::where('tokenable_id', $user->id)
                           ->where('tokenable_type', User::class)
                           ->delete();

        // Log the revocation
        \App\Models\AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'token_revoked',
            'event_description' => "Revoked all {$tokenCount} mobile devices for user {$user->name}",
            'model_type' => User::class,
            'model_id' => $user->id,
            'metadata' => [
                'token_count' => $tokenCount,
                'revoked_by' => Auth::user()->name,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()
            ->route('admin.devices.index')
            ->with('success', "All devices for {$user->name} have been revoked. ({$tokenCount} tokens)");
    }
}