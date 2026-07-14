<?php

namespace App\Http\Controllers\Admin\Security;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ExportAudit;
use App\Support\TabularExport;
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

        return view('admin.devices.mobile.index', [
            'devices' => $this->deviceInventory($request),
            'bhws' => User::with(['assignedBarangay', 'assignedPurok'])
                ->where('role', 'bhw')
                ->where('approval_status', User::APPROVAL_APPROVED)
                ->active()
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Export the mobile device inventory.
     */
    public function export(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        $devices = $this->deviceInventory($request);
        $format = $request->string('format', 'csv')->toString();
        $timestamp = now()->format('Y-m-d_His');

        $columns = [
            'BHW' => fn (array $device) => $device['user']->name,
            'Email' => fn (array $device) => $device['user']->email,
            'Device Name' => 'device_name',
            'Assignment' => fn (array $device) => $device['user']->assignment_label,
            'Last Used' => fn (array $device) => $device['last_used']?->format('Y-m-d H:i:s') ?? 'Never',
            'Created At' => fn (array $device) => $device['created_at']?->format('Y-m-d H:i:s'),
        ];

        $filters = [
            'Search' => $request->string('search')->toString(),
        ];

        ExportAudit::log('mobile device inventory', $format, [
            'model_type' => User::class,
            'record_count' => $devices->count(),
            'filters' => array_filter($filters),
        ]);

        return match ($format) {
            'csv' => TabularExport::csv("mobile_devices_{$timestamp}.csv", $columns, $devices),
            'xlsx' => TabularExport::xlsx("mobile_devices_{$timestamp}.xlsx", 'Mobile Devices', $columns, $devices),
            'pdf' => TabularExport::pdf("mobile_devices_{$timestamp}.pdf", 'Mobile Device Inventory', $columns, $devices, $filters),
            default => abort(404),
        };
    }

    /**
     * Issue a new mobile device token for a BHW.
     */
    public function issue(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::findOrFail($validated['user_id']);

        if ($user->role !== 'bhw' || ! $user->isApproved() || ! $user->is_active) {
            return back()->with('error', 'Only active, approved BHW accounts can receive mobile tokens.');
        }

        $revokedTokens = $user->tokens()->count();
        $user->tokens()->delete();

        $plainTextToken = $user->createToken($validated['device_name'], ['mobile'])->plainTextToken;

        \App\Models\AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'created',
            'event_description' => "Issued mobile device token '{$validated['device_name']}' for {$user->name}",
            'model_type' => User::class,
            'model_id' => $user->id,
            'metadata' => [
                'device_name' => $validated['device_name'],
                'issued_for' => $user->email,
                'revoked_existing_tokens' => $revokedTokens,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()
            ->route('admin.devices.index')
            ->with('success', "Device token issued for {$user->name}.")
            ->with('issued_token', $plainTextToken)
            ->with('issued_device_name', $validated['device_name'])
            ->with('issued_user_name', $user->name)
            ->with('revoked_existing_tokens', $revokedTokens);
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

    /**
     * Build a searchable device inventory.
     */
    private function deviceInventory(Request $request)
    {
        $query = User::where('role', 'bhw')
            ->where('approval_status', User::APPROVAL_APPROVED)
            ->active()
            ->with(['assignedBarangay', 'assignedPurok']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $devices = collect();

        foreach ($query->get() as $bhw) {
            $tokens = PersonalAccessToken::where('tokenable_id', $bhw->id)
                ->where('tokenable_type', User::class)
                ->latest()
                ->get();

            foreach ($tokens as $token) {
                $devices->push([
                    'user' => $bhw,
                    'token' => $token,
                    'device_name' => $token->name ?? 'Unknown Device',
                    'last_used' => $token->last_used_at,
                    'created_at' => $token->created_at,
                    'is_stale' => ($token->last_used_at && $token->last_used_at->lte(now()->subDays(30)))
                        || (! $token->last_used_at && $token->created_at->lte(now()->subDays(7))),
                    'stale_reason' => $token->last_used_at
                        ? 'Last used '.$token->last_used_at->diffForHumans()
                        : 'Never used after issuance',
                ]);
            }
        }

        return $devices;
    }
}
