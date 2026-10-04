<?php

namespace App\Policies;

use App\Models\ResidentLifecycleEvent;
use App\Models\User;

// No public timeline or direct mutation authority is activated in L1B.
class ResidentLifecycleEventPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, ResidentLifecycleEvent $event): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ResidentLifecycleEvent $event): bool
    {
        return false;
    }

    public function delete(User $user, ResidentLifecycleEvent $event): bool
    {
        return false;
    }

    public function restore(User $user, ResidentLifecycleEvent $event): bool
    {
        return false;
    }

    public function forceDelete(User $user, ResidentLifecycleEvent $event): bool
    {
        return false;
    }
}
