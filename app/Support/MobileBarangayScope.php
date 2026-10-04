<?php

namespace App\Support;

use App\Models\User;

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
}
