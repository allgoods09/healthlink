<?php

namespace App\Policies;

use App\Models\ArchivedRecord;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ArchivedRecordPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, ArchivedRecord $archivedRecord): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, ArchivedRecord $archivedRecord): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, ArchivedRecord $archivedRecord): bool
    {
        return $user->role === 'admin';
    }
}
