<?php

namespace App\Policies;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BarangayPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any barangays (index).
     * All authenticated users can view barangays.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view a specific barangay (show).
     * All authenticated users can view barangays.
     */
    public function view(User $user, Barangay $barangay): bool
    {
        return true;
    }

    /**
     * Determine if the user can create barangays (store).
     * Only Admin can create barangays.
     */
    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can update a barangay (update).
     * Only Admin can update barangays.
     */
    public function update(User $user, Barangay $barangay): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can delete a barangay (destroy).
     * Only Admin can delete barangays.
     */
    public function delete(User $user, Barangay $barangay): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can restore a barangay (restore).
     * Only Admin can restore barangays.
     */
    public function restore(User $user, Barangay $barangay): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can force delete a barangay (forceDelete).
     * Only Admin can permanently delete barangays.
     */
    public function forceDelete(User $user, Barangay $barangay): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can toggle a barangay's status.
     * Only Admin can toggle barangay status.
     */
    public function toggleStatus(User $user, Barangay $barangay): bool
    {
        return $user->role === 'admin';
    }
}