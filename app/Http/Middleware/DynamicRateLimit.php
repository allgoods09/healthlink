<?php

namespace App\Http\Middleware;

use App\Support\RateLimitState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class DynamicRateLimit
{
    /**
     * Handle an incoming request with a setting-backed rate limit profile.
     */
    public function handle(Request $request, Closure $next, string $profile): Response
    {
        $config = RateLimitState::profileConfig($profile);
        $key = RateLimitState::keyForRequest($profile, $request);

        RateLimitState::trackRequest($profile, $request, $key);

        if (RateLimiter::tooManyAttempts($key, $config['max_attempts'])) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'message' => "Too many {$config['label']} requests. Please try again later.",
                'profile' => $profile,
                'retry_after_seconds' => $retryAfter,
            ], 429, [
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $config['max_attempts'],
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        RateLimiter::hit($key, $config['decay_seconds']);

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $config['max_attempts']);
        $response->headers->set('X-RateLimit-Remaining', (string) max(RateLimiter::remaining($key, $config['max_attempts']), 0));

        return $response;
    }
}
