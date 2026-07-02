<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any users (index).
     * Only Admin can view the user list.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can view a specific user (show).
     * Admin can view all users.
     * MHO can view BHWs and BNS under their municipality.
     * PHN can view BHWs and BNS under their municipality.
     * Users can view their own profile.
     */
    public function view(User $user, User $targetUser): bool
    {
        // Admin can view everyone
        if ($user->role === 'admin') {
            return true;
        }

        // Users can view their own profile
        if ($user->id === $targetUser->id) {
            return true;
        }

        // MHO and PHN can view BHWs and BNS
        if (in_array($user->role, ['mho', 'phn'])) {
            // They can view anyone who has a barangay assignment
            // (BHWs, BNS, Secretaries)
            return !is_null($targetUser->assigned_barangay_id);
        }

        // Everyone else cannot view others
        return false;
    }

    /**
     * Determine if the user can create users (store).
     * Only Admin can create users.
     */
    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can update a user (update).
     * Admin can update everyone.
     * MHO can update BHWs, BNS, and Secretaries in their municipality.
     * Users can update their own profile (limited).
     */
    public function update(User $user, User $targetUser): bool
    {
        // Admin can update everyone
        if ($user->role === 'admin') {
            return true;
        }

        // Users can update their own profile (but not role or assignments)
        if ($user->id === $targetUser->id) {
            return true;
        }

        // MHO can update local field workers (BHWs, BNS, Secretaries)
        if ($user->role === 'mho') {
            // Can update if target is a BHW, BNS, or Secretary
            return in_array($targetUser->role, ['bhw', 'bns', 'secretary']);
        }

        return false;
    }

    /**
     * Determine if the user can delete a user (destroy).
     * Only Admin can delete users (soft delete).
     */
    public function delete(User $user, User $targetUser): bool
    {
        // Admin can delete anyone except themselves
        if ($user->role === 'admin') {
            return $user->id !== $targetUser->id;
        }

        return false;
    }

    /**
     * Determine if the user can restore a user (restore).
     * Only Admin can restore deleted users.
     */
    public function restore(User $user, User $targetUser): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can force delete a user (forceDelete).
     * Only Admin can permanently delete users.
     */
    public function forceDelete(User $user, User $targetUser): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can toggle a user's status (toggle).
     * Admin can toggle anyone.
     * MHO can toggle BHWs, BNS, and Secretaries.
     */
    public function toggleStatus(User $user, User $targetUser): bool
    {
        // Admin can toggle everyone
        if ($user->role === 'admin') {
            return $user->id !== $targetUser->id; // Can't toggle self
        }

        // MHO can toggle field workers
        if ($user->role === 'mho') {
            return in_array($targetUser->role, ['bhw', 'bns', 'secretary']);
        }

        return false;
    }

    /**
     * Determine if the user can reset a user's password (reset).
     * Admin can reset anyone.
     * MHO can reset BHWs, BNS, and Secretaries.
     */
    public function resetPassword(User $user, User $targetUser): bool
    {
        // Admin can reset everyone
        if ($user->role === 'admin') {
            return true;
        }

        // MHO can reset field workers
        if ($user->role === 'mho') {
            return in_array($targetUser->role, ['bhw', 'bns', 'secretary']);
        }

        return false;
    }

    /**
     * Determine if the user can assign roles to a user.
     * Only Admin can assign roles.
     */
    public function assignRole(User $user, User $targetUser): bool
    {
        return $user->role === 'admin';
    }
}