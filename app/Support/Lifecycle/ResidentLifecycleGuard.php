<?php

namespace App\Support\Lifecycle;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\ProfileUpdateRequest;
use App\Models\Resident;
use App\Models\User;
use App\Support\BarangayOfficialsRegistry;
use Illuminate\Validation\ValidationException;

// Dormant checks only. Future transition services must call these under their own transaction/row locks.
class ResidentLifecycleGuard
{
    public function requireResident(int $id, ?int $expectedVersion = null, bool $requireActive = false): Resident
    {
        $resident = Resident::find($id);
        if (! $resident) {
            $this->fail('Resident is no longer available.');
        }
        if ($expectedVersion !== null && $resident->lifecycle_version !== $expectedVersion) {
            $this->fail('Resident lifecycle has changed. Refresh before continuing.');
        }
        if ($requireActive && ! $resident->isActive()) {
            $this->fail('An active resident is required.');
        }

        return $resident;
    }

    public function requireOwnership(int $residentId, ?int $householdId = null, ?int $purokId = null, ?int $barangayId = null): Household
    {
        $resident = $this->requireResident($residentId);
        $household = $resident->household()->with('purok.barangay')->first();
        if (! $household || ! $household->purok || ! $household->purok->barangay
            || ($householdId !== null && $householdId !== (int) $household->id)
            || ($purokId !== null && $purokId !== (int) $household->purok_id)
            || ($barangayId !== null && $barangayId !== (int) $household->purok->barangay_id)) {
            $this->fail('Resident ownership has changed or is unavailable.');
        }

        return $household;
    }

    public function requireOperationalSecretary(int $userId, int $barangayId): User
    {
        $barangay = Barangay::find($barangayId);
        $user = User::find($userId);
        if (! $barangay || ! $user || ! $user->isOperationalSecretaryFor($barangay)
            || app(BarangayOfficialsRegistry::class)->operationalSecretary($barangay)?->id !== $user->id) {
            $this->fail('An operational Secretary assigned to this barangay is required.');
        }

        return $user;
    }

    public function hasPendingCorrections(int $residentId, array $householdIds = []): bool
    {
        $resident = $this->requireResident($residentId);
        $householdIds = array_unique([$resident->household_id, ...$householdIds]);

        return ProfileUpdateRequest::where('request_status', ProfileUpdateRequest::STATUS_PENDING)
            ->where(function ($query) use ($residentId, $householdIds): void {
                $query->where(fn ($q) => $q->where('subject_type', ProfileUpdateRequest::SUBJECT_RESIDENT)->where('subject_id', $residentId))
                    ->orWhere(fn ($q) => $q->where('subject_type', ProfileUpdateRequest::SUBJECT_HOUSEHOLD)->whereIn('subject_id', $householdIds));
            })->exists();
    }

    public function requireNoPendingCorrections(int $residentId, array $householdIds = []): void
    {
        if ($this->hasPendingCorrections($residentId, $householdIds)) {
            $this->fail('Resolve pending profile corrections before continuing.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['resident_lifecycle' => $message]);
    }
}
