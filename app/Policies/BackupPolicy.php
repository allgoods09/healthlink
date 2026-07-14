<?php

namespace App\Policies;

use App\Models\Backup;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BackupPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, Backup $backup): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, Backup $backup): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, Backup $backup): bool
    {
        return $user->role === 'admin';
    }

    public function restore(User $user, Backup $backup): bool
    {
        return $user->role === 'admin';
    }
}
