<?php

namespace App\Policies;

use App\Models\Household;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class HouseholdPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any households (index).
     * All authenticated users can view households.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view a specific household (show).
     * Users can only view households within their scope.
     */
    public function view(User $user, Household $household): bool
    {
        // Admin, MHO, PHN can view all households
        if (in_array($user->role, ['admin', 'mho', 'phn'])) {
            return true;
        }

        // Secretaries and BNS can view households in their barangay
        if (in_array($user->role, ['secretary', 'bns'])) {
            return $user->assigned_barangay_id === $household->purok->barangay_id;
        }

        // BHWs can only view households in their assigned purok
        if ($user->role === 'bhw') {
            return $user->assigned_purok_id === $household->purok_id;
        }

        return false;
    }

    /**
     * Determine if the user can create households (store).
     */
    public function create(User $user): bool
    {
        // Admin, MHO, PHN can create households
        if (in_array($user->role, ['admin', 'mho', 'phn'])) {
            return true;
        }

        // Secretaries and BNS can create households in their barangay
        if (in_array($user->role, ['secretary', 'bns'])) {
            return true;
        }

        // BHWs can create households in their assigned purok
        if ($user->role === 'bhw') {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can update a household (update).
     */
    public function update(User $user, Household $household): bool
    {
        // Admin, MHO, PHN can update all households
        if (in_array($user->role, ['admin', 'mho', 'phn'])) {
            return true;
        }

        // Secretaries and BNS can update households in their barangay
        if (in_array($user->role, ['secretary', 'bns'])) {
            return $user->assigned_barangay_id === $household->purok->barangay_id;
        }

        // BHWs can only update households in their assigned purok
        if ($user->role === 'bhw') {
            return $user->assigned_purok_id === $household->purok_id;
        }

        return false;
    }

    /**
     * Determine if the user can delete a household (destroy).
     */
    public function delete(User $user, Household $household): bool
    {
        // Only Admin can delete households
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can restore a household (restore).
     */
    public function restore(User $user, Household $household): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can force delete a household (forceDelete).
     */
    public function forceDelete(User $user, Household $household): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can toggle a household's status.
     */
    public function toggleStatus(User $user, Household $household): bool
    {
        // Admin can toggle all
        if ($user->role === 'admin') {
            return true;
        }

        // MHO, PHN can toggle all households
        if (in_array($user->role, ['mho', 'phn'])) {
            return true;
        }

        // Secretaries and BNS can toggle households in their barangay
        if (in_array($user->role, ['secretary', 'bns'])) {
            return $user->assigned_barangay_id === $household->purok->barangay_id;
        }

        return false;
    }
}