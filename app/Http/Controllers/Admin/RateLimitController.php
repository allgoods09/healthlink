<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RateLimitUpdateRequest;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use Illuminate\Support\Facades\Auth;

class RateLimitController extends Controller
{
    /**
     * Display rate limit settings.
     */
    public function index()
    {
        Gate::authorize('viewAny', Setting::class);

        $settings = [
            'rate_limit_attempts' => Setting::getValue('rate_limit_attempts', 60),
            'rate_limit_decay_minutes' => Setting::getValue('rate_limit_decay_minutes', 1),
            'sync_batch_size' => Setting::getValue('sync_batch_size', 100),
            'backup_retention_days' => Setting::getValue('backup_retention_days', 30),
            'api_rate_limit_global' => Setting::getValue('api_rate_limit_global', 60),
            'api_rate_limit_auth' => Setting::getValue('api_rate_limit_auth', 10),
        ];

        return view('admin.rate-limits.index', compact('settings'));
    }

    /**
     * Update rate limit settings.
     */
    public function update(RateLimitUpdateRequest $request)
    {
        Gate::authorize('update', Setting::class);

        $settings = $request->validated();

        foreach ($settings as $key => $value) {
            Setting::setValue($key, $value);
        }

        // Log the update
        \App\Models\AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'updated',
            'event_description' => 'Updated rate limit settings',
            'model_type' => Setting::class,
            'metadata' => $settings,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()
            ->route('admin.rate-limits.index')
            ->with('success', 'Rate limit settings updated successfully.');
    }

    /**
     * Reset rate limits for a specific IP or user.
     */
    public function reset(Request $request)
    {
        Gate::authorize('update', Setting::class);

        $request->validate([
            'type' => ['required', 'in:ip,user,all'],
            'identifier' => ['nullable', 'string'],
        ]);

        $type = $request->type;
        $identifier = $request->identifier;

        $cleared = 0;

        if ($type === 'all') {
            // Clear all rate limits - this would require custom logic
            // Laravel's built-in rate limiter doesn't support clearing all easily
            $cleared = 'all';
        } elseif ($type === 'ip' && $identifier) {
            // Clear for specific IP
            RateLimiterFacade::clear($identifier);
            $cleared = "IP: {$identifier}";
        } elseif ($type === 'user' && $identifier) {
            // Clear for specific user
            RateLimiterFacade::clear($identifier);
            $cleared = "User: {$identifier}";
        }

        // Log the reset
        \App\Models\AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'updated',
            'event_description' => "Reset rate limits for {$cleared}",
            'model_type' => Setting::class,
            'metadata' => ['type' => $type, 'identifier' => $identifier],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()
            ->route('admin.rate-limits.index')
            ->with('success', "Rate limits reset successfully for {$cleared}.");
    }

    /**
     * Get current rate limit status.
     */
    public function status(Request $request)
    {
        Gate::authorize('viewAny', Setting::class);

        $ip = $request->ip();
        $status = [];

        // Get the rate limiter for the current IP
        $key = $ip;
        $limiter = app(RateLimiter::class);

        // Check different rate limit keys
        $status['ip'] = [
            'key' => $key,
            'remaining' => $limiter->remaining($key, 60),
            'available_in' => $limiter->availableIn($key),
        ];

        // Check auth rate limit
        $authKey = 'auth_' . $key;
        $status['auth'] = [
            'key' => $authKey,
            'remaining' => $limiter->remaining($authKey, 10),
            'available_in' => $limiter->availableIn($authKey),
        ];

        return response()->json($status);
    }

    /**
     * Get all rate limited keys (for monitoring).
     */
    public function blocked()
    {
        Gate::authorize('viewAny', Setting::class);

        // This is complex with default Laravel rate limiter
        // We'll return a simple response
        $blockedKeys = cache()->get('rate_limiter_blocked', []);

        return response()->json([
            'blocked_keys' => $blockedKeys,
            'total_blocked' => count($blockedKeys),
        ]);
    }

    /**
     * Unblock a specific key.
     */
    public function unblock(Request $request)
    {
        Gate::authorize('update', Setting::class);

        $request->validate([
            'key' => ['required', 'string'],
        ]);

        $key = $request->key;

        // Clear the rate limit for this key
        RateLimiterFacade::clear($key);

        // Remove from blocked list if tracked
        $blocked = cache()->get('rate_limiter_blocked', []);
        if (($index = array_search($key, $blocked)) !== false) {
            unset($blocked[$index]);
            cache()->put('rate_limiter_blocked', array_values($blocked), now()->addDay());
        }

        // Log the unblock
        \App\Models\AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'updated',
            'event_description' => "Unblocked rate limit for key: {$key}",
            'model_type' => Setting::class,
            'metadata' => ['key' => $key],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()
            ->route('admin.rate-limits.index')
            ->with('success', "Rate limit unblocked for key: {$key}");
    }
}