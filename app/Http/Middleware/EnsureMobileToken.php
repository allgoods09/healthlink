<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileToken
{
    /**
     * Ensure the request is authenticated with a mobile API token.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user || ! $token) {
            return response()->json([
                'message' => 'A valid mobile access token is required.',
            ], 401);
        }

        if ($user->role !== 'bhw') {
            return response()->json([
                'message' => 'Only Barangay Health Worker accounts can access the mobile API.',
            ], 403);
        }

        if (! $user->isApproved() || ! $user->is_active) {
            return response()->json([
                'message' => 'This account is no longer allowed to access the mobile API.',
            ], 403);
        }

        return $next($request);
    }
}
