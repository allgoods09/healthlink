<?php

namespace App\Policies;

use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SyncLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'bns']);
    }

    public function view(User $user, SyncLog $syncLog): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'bns') {
            return (int) $syncLog->user?->assigned_barangay_id === (int) $user->assigned_barangay_id;
        }

        return false;
    }
}
