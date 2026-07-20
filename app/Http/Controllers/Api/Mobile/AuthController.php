<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\MobileForgotPasswordRequest;
use App\Http\Requests\Mobile\MobileLoginRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\MobileReleaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function __construct(
        private readonly MobileReleaseManager $releaseManager
    ) {
    }

    /**
     * Authenticate a BHW and issue a single active mobile token.
     */
    public function login(MobileLoginRequest $request): JsonResponse
    {
        $settings = $this->releaseManager->releaseSettings();

        if (! $settings['login_enabled']) {
            return response()->json([
                'message' => $settings['maintenance_message']
                    ?: 'Mobile sign-in is temporarily disabled while the HealthLink BHW app is being updated.',
                'maintenance' => $settings,
                'release' => $this->releaseManager->releasePayload(),
            ], 503);
        }

        $request->ensureIsNotRateLimited();

        $user = User::query()
            ->with(['assignedBarangay', 'assignedPurok'])
            ->whereRaw('LOWER(email) = ?', [strtolower($request->string('email')->toString())])
            ->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            $request->hitRateLimiter();

            return response()->json([
                'message' => trans('auth.failed'),
                'errors' => [
                    'email' => [trans('auth.failed')],
                ],
            ], 422);
        }

        if ($user->role !== 'bhw') {
            return response()->json([
                'message' => 'Only Barangay Health Worker accounts can sign in to the mobile app.',
            ], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Verify your email address on the web before signing in to the mobile app.',
            ], 403);
        }

        if (! $user->isApproved()) {
            return response()->json([
                'message' => match ($user->approval_status) {
                    User::APPROVAL_PENDING => 'Your account is still pending approval.',
                    User::APPROVAL_REJECTED => 'Your registration has been rejected. Please contact an administrator.',
                    default => 'Your account cannot access the mobile app yet.',
                },
            ], 403);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Your account is inactive. Please contact an administrator.',
            ], 403);
        }

        if (! $user->assigned_barangay_id || ! $user->assigned_purok_id) {
            return response()->json([
                'message' => 'Your BHW account is missing a barangay or purok assignment.',
            ], 403);
        }

        $request->clearRateLimiter();

        $revokedTokens = $user->tokens()->count();
        $user->tokens()->delete();

        $token = $user->createToken(
            $request->string('device_name')->toString(),
            ['mobile']
        );

        AuditLog::log([
            'user_id' => $user->id,
            'event_type' => 'login',
            'event_description' => "Mobile login succeeded for {$user->email}",
            'model_type' => User::class,
            'model_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'device_name' => $request->device_name,
                'device_id' => $request->device_id,
                'device_model' => $request->device_model,
                'device_platform' => $request->device_platform,
                'app_version' => $request->app_version,
                'locale' => $request->locale,
                'revoked_existing_tokens' => $revokedTokens,
            ],
        ]);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'server_time' => now()->toIso8601String(),
            'single_device_enforced' => true,
            'revoked_tokens' => $revokedTokens,
            'maintenance' => $settings,
            'release' => $this->releaseManager->releasePayload(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'assignment_label' => $user->assignment_label,
                'assigned_barangay_id' => $user->assigned_barangay_id,
                'assigned_purok_id' => $user->assigned_purok_id,
            ],
        ]);
    }

    /**
     * Revoke the current mobile access token.
     */
    public function logout(): JsonResponse
    {
        $user = request()->user();

        AuditLog::log([
            'user_id' => $user->id,
            'event_type' => 'logout',
            'event_description' => "Mobile logout for {$user->email}",
            'model_type' => User::class,
            'model_id' => $user->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => [
                'token_name' => $user->currentAccessToken()?->name,
            ],
        ]);

        $user->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Send a password-reset link for a mobile user.
     */
    public function forgotPassword(MobileForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => __($status),
                'errors' => [
                    'email' => [__($status)],
                ],
            ], 422);
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($request->string('email')->toString())])
            ->first();

        AuditLog::log([
            'user_id' => $user?->id,
            'event_type' => 'password_reset',
            'event_description' => "Mobile password reset link requested for {$request->email}",
            'model_type' => User::class,
            'model_id' => $user?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'channel' => 'mobile',
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => __($status),
        ]);
    }
}
