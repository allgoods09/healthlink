<?php

namespace App\Support;

use App\Models\User;
use App\Models\Purok;

final class MobileBarangayScope
{
    public const DENIED_MESSAGE = 'Your BHW account needs an active barangay assignment to access the mobile app. Please contact an administrator.';

    public static function requireBarangayId(User $user): int
    {
        // Query the relationship rather than trusting a previously loaded barangay.
        abort_unless(
            $user->assigned_barangay_id && $user->assignedBarangay()->active()->exists(),
            403,
            self::DENIED_MESSAGE
        );

        return (int) $user->assigned_barangay_id;
    }

    public static function requirePurok(User $user): Purok
    {
        abort_unless($user->role === 'bhw' && $user->isApproved() && $user->is_active, 403,
            'This account is no longer allowed to access the mobile API.');
        $barangayId = self::requireBarangayId($user);
        $purok = Purok::query()->active()->whereKey($user->assigned_purok_id)
            ->where('barangay_id', $barangayId)->first();

        abort_unless($purok, 403,
            'Your BHW account needs an active purok assignment in your assigned barangay. Please contact an administrator.');

        return $purok;
    }
}
