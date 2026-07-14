<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AuditLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'secretary']);
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return in_array($user->role, ['admin', 'secretary']);
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return $user->role === 'admin';
    }
}
