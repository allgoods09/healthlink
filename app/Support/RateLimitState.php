<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class RateLimitState
{
    private const REGISTRY_CACHE_KEY = 'rate_limiter_registry';

    /**
     * Get the live configuration for a named rate-limit profile.
     */
    public static function profileConfig(string $profile): array
    {
        $decaySeconds = max((int) Setting::getValue('rate_limit_decay_minutes', 1), 1) * 60;

        return match ($profile) {
            'admin-helper' => [
                'label' => 'Admin Helper API',
                'max_attempts' => max((int) Setting::getValue('rate_limit_attempts', 60), 1),
                'decay_seconds' => $decaySeconds,
            ],
            'api-global' => [
                'label' => 'Mobile API',
                'max_attempts' => max((int) Setting::getValue('api_rate_limit_global', 60), 1),
                'decay_seconds' => $decaySeconds,
            ],
            'auth' => [
                'label' => 'Authentication',
                'max_attempts' => max((int) Setting::getValue('api_rate_limit_auth', 10), 1),
                'decay_seconds' => $decaySeconds,
            ],
            default => [
                'label' => Str::headline($profile),
                'max_attempts' => 60,
                'decay_seconds' => $decaySeconds,
            ],
        };
    }

    /**
     * Build the throttle key for request-scoped API rate limits.
     */
    public static function keyForRequest(string $profile, Request $request): string
    {
        $segments = [$profile];

        if ($request->user()) {
            $segments[] = 'user:'.$request->user()->getAuthIdentifier();
        }

        $segments[] = 'ip:'.($request->ip() ?? 'unknown');

        return implode('|', $segments);
    }

    /**
     * Track a request-based throttle key so admins can inspect and clear it later.
     */
    public static function trackRequest(string $profile, Request $request, string $key): void
    {
        self::remember($key, $profile, [
            'ip' => $request->ip(),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'email' => $request->user()?->email,
            'route' => $request->route()?->getName(),
            'path' => $request->path(),
        ]);
    }

    /**
     * Track an authentication throttle key.
     */
    public static function trackAuthKey(string $key, string $email, ?string $ip): void
    {
        self::remember($key, 'auth', [
            'ip' => $ip,
            'email' => Str::lower($email),
            'user_id' => User::query()
                ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
                ->value('id'),
        ]);
    }

    /**
     * Get a current status snapshot for the admin dashboard.
     */
    public static function statusSnapshot(Request $request): array
    {
        $adminHelperKey = self::keyForRequest('admin-helper', $request);
        $mobileApiKey = self::keyForRequest('api-global', $request);
        $authConfig = self::profileConfig('auth');
        $blockedKeys = self::blockedKeys();

        return [
            'profiles' => [
                'admin_helper' => self::keyStatus('admin-helper', $adminHelperKey),
                'mobile_api' => self::keyStatus('api-global', $mobileApiKey),
                'authentication' => [
                    'label' => $authConfig['label'],
                    'max_attempts' => $authConfig['max_attempts'],
                    'decay_seconds' => $authConfig['decay_seconds'],
                ],
            ],
            'tracked_total' => count(self::trackedKeys()),
            'blocked_total' => count($blockedKeys),
            'blocked_keys' => $blockedKeys,
        ];
    }

    /**
     * List currently blocked tracked keys.
     */
    public static function blockedKeys(): array
    {
        $blocked = [];

        foreach (self::trackedKeys() as $key => $entry) {
            $profile = $entry['profile'] ?? 'admin-helper';
            $config = self::profileConfig($profile);

            if (! RateLimiter::tooManyAttempts($key, $config['max_attempts'])) {
                continue;
            }

            $blocked[] = [
                'key' => $key,
                'profile' => $profile,
                'label' => $config['label'],
                'ip' => $entry['ip'] ?? null,
                'user_id' => $entry['user_id'] ?? null,
                'email' => $entry['email'] ?? null,
                'route' => $entry['route'] ?? null,
                'path' => $entry['path'] ?? null,
                'available_in' => RateLimiter::availableIn($key),
                'max_attempts' => $config['max_attempts'],
                'last_seen_at' => $entry['last_seen_at'] ?? null,
            ];
        }

        usort($blocked, fn (array $left, array $right) => ($right['available_in'] ?? 0) <=> ($left['available_in'] ?? 0));

        return $blocked;
    }

    /**
     * Clear a single tracked key and forget its registry metadata.
     */
    public static function clearKey(string $key): bool
    {
        RateLimiter::clear($key);

        $registry = self::trackedKeys();
        $hadEntry = array_key_exists($key, $registry);

        unset($registry[$key]);
        self::storeRegistry($registry);

        return $hadEntry;
    }

    /**
     * Clear tracked keys by IP, user/email, or globally.
     */
    public static function clearMatching(string $type, ?string $identifier = null): array
    {
        $registry = self::trackedKeys();
        $keysToClear = [];

        if ($type === 'all') {
            $keysToClear = array_keys($registry);
        }

        if ($type === 'ip' && $identifier) {
            foreach ($registry as $key => $entry) {
                if (($entry['ip'] ?? null) === $identifier) {
                    $keysToClear[] = $key;
                }
            }
        }

        if ($type === 'user' && $identifier) {
            foreach ($registry as $key => $entry) {
                if (self::matchesUserIdentifier($entry, $identifier)) {
                    $keysToClear[] = $key;
                }
            }
        }

        foreach ($keysToClear as $key) {
            RateLimiter::clear($key);
            unset($registry[$key]);
        }

        self::storeRegistry($registry);

        return array_values(array_unique($keysToClear));
    }

    /**
     * Get all tracked throttle keys with metadata.
     */
    public static function trackedKeys(): array
    {
        return cache()->get(self::REGISTRY_CACHE_KEY, []);
    }

    /**
     * Persist throttle metadata for later admin inspection.
     */
    private static function remember(string $key, string $profile, array $metadata): void
    {
        $registry = self::trackedKeys();
        $registry[$key] = array_filter([
            'profile' => $profile,
            'ip' => $metadata['ip'] ?? null,
            'user_id' => $metadata['user_id'] ?? null,
            'email' => $metadata['email'] ?? null,
            'route' => $metadata['route'] ?? null,
            'path' => $metadata['path'] ?? null,
            'last_seen_at' => now()->toIso8601String(),
        ], fn (mixed $value) => ! is_null($value) && $value !== '');

        self::storeRegistry($registry);
    }

    /**
     * Build current key metrics for the status endpoint.
     */
    private static function keyStatus(string $profile, string $key): array
    {
        $config = self::profileConfig($profile);

        return [
            'key' => $key,
            'label' => $config['label'],
            'max_attempts' => $config['max_attempts'],
            'remaining' => RateLimiter::remaining($key, $config['max_attempts']),
            'available_in' => RateLimiter::availableIn($key),
        ];
    }

    /**
     * Persist the registry or clear it entirely when empty.
     */
    private static function storeRegistry(array $registry): void
    {
        if ($registry === []) {
            cache()->forget(self::REGISTRY_CACHE_KEY);

            return;
        }

        cache()->put(self::REGISTRY_CACHE_KEY, $registry, now()->addDays(7));
    }

    /**
     * Check whether tracked metadata matches a user-facing identifier.
     */
    private static function matchesUserIdentifier(array $entry, string $identifier): bool
    {
        $identifier = trim($identifier);
        $normalizedIdentifier = Str::lower($identifier);
        $user = is_numeric($identifier)
            ? User::find((int) $identifier)
            : User::query()->whereRaw('LOWER(email) = ?', [$normalizedIdentifier])->first();

        $candidateEmails = array_filter([
            $normalizedIdentifier,
            $user?->email ? Str::lower($user->email) : null,
        ]);

        $candidateUserIds = array_filter([
            is_numeric($identifier) ? (string) $identifier : null,
            $user?->id ? (string) $user->id : null,
        ]);

        return in_array(Str::lower((string) ($entry['email'] ?? '')), $candidateEmails, true)
            || in_array((string) ($entry['user_id'] ?? ''), $candidateUserIds, true);
    }
}
