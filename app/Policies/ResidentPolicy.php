<?php

namespace App\Policies;

use App\Models\Resident;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ResidentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any residents (index).
     * All authenticated users can view residents.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view a specific resident (show).
     * Users can only view residents within their scope.
     */
    public function view(User $user, Resident $resident): bool
    {
        // Admin, MHO, PHN can view all residents
        if (in_array($user->role, ['admin', 'mho', 'phn'])) {
            return true;
        }

        // Secretaries and BNS can view residents in their barangay
        if (in_array($user->role, ['secretary', 'bns'])) {
            return $user->assigned_barangay_id === $resident->household->purok->barangay_id;
        }

        // BHWs can only view residents in their assigned purok
        if ($user->role === 'bhw') {
            return $user->assigned_purok_id === $resident->household->purok_id;
        }

        return false;
    }

    /**
     * Determine if the user can create residents (store).
     */
    public function create(User $user): bool
    {
        // Admin, MHO, PHN can create residents
        if (in_array($user->role, ['admin', 'mho', 'phn'])) {
            return true;
        }

        // Secretaries and BNS can create residents in their barangay
        if (in_array($user->role, ['secretary', 'bns'])) {
            return true;
        }

        // BHWs can create residents in their assigned purok
        if ($user->role === 'bhw') {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can update a resident (update).
     */
    public function update(User $user, Resident $resident): bool
    {
        // Admin, MHO, PHN can update all residents
        if (in_array($user->role, ['admin', 'mho', 'phn'])) {
            return true;
        }

        // Secretaries and BNS can update residents in their barangay
        if (in_array($user->role, ['secretary', 'bns'])) {
            return $user->assigned_barangay_id === $resident->household->purok->barangay_id;
        }

        // BHWs can only update residents in their assigned purok
        if ($user->role === 'bhw') {
            return $user->assigned_purok_id === $resident->household->purok_id;
        }

        return false;
    }

    /**
     * Determine if the user can delete a resident (destroy).
     */
    public function delete(User $user, Resident $resident): bool
    {
        // Only Admin can delete residents
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can restore a resident (restore).
     */
    public function restore(User $user, Resident $resident): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can force delete a resident (forceDelete).
     */
    public function forceDelete(User $user, Resident $resident): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can toggle a resident's status.
     */
    public function toggleStatus(User $user, Resident $resident): bool
    {
        // Admin can toggle all
        if ($user->role === 'admin') {
            return true;
        }

        // MHO, PHN can toggle all residents
        if (in_array($user->role, ['mho', 'phn'])) {
            return true;
        }

        // Secretaries and BNS can toggle residents in their barangay
        if (in_array($user->role, ['secretary', 'bns'])) {
            return $user->assigned_barangay_id === $resident->household->purok->barangay_id;
        }

        return false;
    }

    /**
     * Determine if the user can export resident data.
     */
    public function export(User $user): bool
    {
        // Admin, MHO, PHN can export all data
        if (in_array($user->role, ['admin', 'mho', 'phn'])) {
            return true;
        }

        // Secretaries and BNS can export data from their barangay
        if (in_array($user->role, ['secretary', 'bns'])) {
            return true;
        }

        return false;
    }
}