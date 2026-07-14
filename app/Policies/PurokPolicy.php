<?php

namespace App\Policies;

use App\Models\Purok;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurokPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any puroks (index).
     * All authenticated users can view puroks.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view a specific purok (show).
     * All authenticated users can view puroks.
     */
    public function view(User $user, Purok $purok): bool
    {
        if (in_array($user->role, ['admin', 'mho', 'phn'])) {
            return true;
        }

        if (in_array($user->role, ['secretary', 'bns'])) {
            return (int) $purok->barangay_id === (int) $user->assigned_barangay_id;
        }

        if ($user->role === 'bhw') {
            return (int) $purok->id === (int) $user->assigned_purok_id;
        }

        return false;
    }

    /**
     * Determine if the user can create puroks (store).
     * Only Admin can create puroks.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'secretary']);
    }

    /**
     * Determine if the user can update a purok (update).
     * Only Admin can update puroks.
     */
    public function update(User $user, Purok $purok): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'secretary'
            && (int) $purok->barangay_id === (int) $user->assigned_barangay_id;
    }

    /**
     * Determine if the user can delete a purok (destroy).
     * Only Admin can delete puroks.
     */
    public function delete(User $user, Purok $purok): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can restore a purok (restore).
     * Only Admin can restore puroks.
     */
    public function restore(User $user, Purok $purok): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can force delete a purok (forceDelete).
     * Only Admin can permanently delete puroks.
     */
    public function forceDelete(User $user, Purok $purok): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can toggle a purok's status.
     * Only Admin can toggle purok status.
     */
    public function toggleStatus(User $user, Purok $purok): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'secretary'
            && (int) $purok->barangay_id === (int) $user->assigned_barangay_id;
    }
}
