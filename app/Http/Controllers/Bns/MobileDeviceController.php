<?php

namespace App\Http\Controllers\Bns;

use App\Http\Controllers\Bns\Concerns\InteractsWithBnsScope;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

class MobileDeviceController extends Controller
{
    use InteractsWithBnsScope;

    public function index(Request $request): View
    {
        return view('admin.devices.mobile.index', [
            'layout' => 'layouts.portal',
            'routePrefix' => 'bns',
            'pageTitle' => 'Device Access - HealthLink BNS',
            'pageHeader' => 'BHW Device Access',
            'canIssue' => false,
            'devices' => $this->deviceInventory($request),
            'bhws' => collect(),
        ]);
    }

    public function export(Request $request)
    {
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

        ExportAudit::log('bns mobile device inventory', $format, [
            'model_type' => User::class,
            'record_count' => $devices->count(),
            'filters' => array_filter([
                'Search' => $request->string('search')->toString(),
            ]),
        ]);

        return match ($format) {
            'csv' => TabularExport::csv("bns_mobile_devices_{$timestamp}.csv", $columns, $devices),
            'xlsx' => TabularExport::xlsx("bns_mobile_devices_{$timestamp}.xlsx", 'BNS Mobile Devices', $columns, $devices),
            'pdf' => TabularExport::pdf("bns_mobile_devices_{$timestamp}.pdf", 'BHW Device Access', $columns, $devices, [
                'Barangay' => $this->bnsUser()->assignedBarangay?->name,
            ]),
            default => abort(404),
        };
    }

    public function revoke(Request $request, int $tokenId): RedirectResponse
    {
        $token = PersonalAccessToken::findOrFail($tokenId);
        $user = User::findOrFail($token->tokenable_id);

        $this->ensureBhwBelongsToBarangay($user);

        $deviceName = $token->name ?? 'Unknown Device';
        $token->delete();

        AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'token_revoked',
            'event_description' => "BNS revoked mobile device '{$deviceName}' for {$user->name}",
            'model_type' => User::class,
            'model_id' => $user->id,
            'metadata' => [
                'device_name' => $deviceName,
                'revoked_by' => Auth::user()?->name,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('bns.devices.index')
            ->with('success', "Device '{$deviceName}' for {$user->name} has been revoked.");
    }

    public function revokeAll(User $user): RedirectResponse
    {
        $this->ensureBhwBelongsToBarangay($user);

        $tokenCount = PersonalAccessToken::query()
            ->where('tokenable_id', $user->id)
            ->where('tokenable_type', User::class)
            ->count();

        PersonalAccessToken::query()
            ->where('tokenable_id', $user->id)
            ->where('tokenable_type', User::class)
            ->delete();

        AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'token_revoked',
            'event_description' => "BNS revoked all {$tokenCount} mobile devices for {$user->name}",
            'model_type' => User::class,
            'model_id' => $user->id,
            'metadata' => [
                'token_count' => $tokenCount,
                'revoked_by' => Auth::user()?->name,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()
            ->route('bns.devices.index')
            ->with('success', "All devices for {$user->name} have been revoked. ({$tokenCount} tokens)");
    }

    private function deviceInventory(Request $request)
    {
        $query = $this->bnsBhwsQuery(false)
            ->where('approval_status', User::APPROVAL_APPROVED)
            ->active()
            ->with(['assignedBarangay', 'assignedPurok']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $devices = collect();

        foreach ($query->get() as $bhw) {
            $tokens = PersonalAccessToken::query()
                ->where('tokenable_id', $bhw->id)
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
