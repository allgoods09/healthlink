<?php

namespace App\Policies;

use App\Models\FieldVisit;
use App\Models\Household;
use App\Models\User;

class FieldVisitPolicy
{
    public function viewHousehold(User $user, Household $household): bool
    {
        $household->loadMissing('purok');
        $barangayId = $household->purok?->barangay_id;
        if ($barangayId === null) {
            return false;
        }

        return match ($user->role) {
            'admin' => true,
            'secretary' => $user->assigned_barangay_id !== null
                && (int) $user->assigned_barangay_id === (int) $barangayId,
            'bhw' => $user->assigned_barangay_id !== null
                && $user->assigned_purok_id !== null
                && (int) $user->assigned_barangay_id === (int) $barangayId
                && (int) $user->assigned_purok_id === (int) $household->purok_id,
            default => false,
        };
    }

    public function view(User $user, FieldVisit $fieldVisit): bool
    {
        $fieldVisit->loadMissing('household.purok');

        return $fieldVisit->household !== null
            && $this->viewHousehold($user, $fieldVisit->household);
    }
}
