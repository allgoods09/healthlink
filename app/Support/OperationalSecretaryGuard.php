<?php

namespace App\Support;

use App\Models\Barangay;
use App\Models\User;
use Closure;
use Illuminate\Validation\ValidationException;

class OperationalSecretaryGuard
{
    public const MESSAGE = 'This barangay already has an active Barangay Secretary. Deactivate, reassign, or update the existing Secretary before assigning another.';

    private const STATE_FIELDS = ['role', 'approval_status', 'is_active', 'email_verified_at', 'deleted_at', 'assigned_barangay_id'];

    public function saveUser(User $user, Closure $save): bool
    {
        if ($user->exists && ! array_intersect(self::STATE_FIELDS, array_keys($user->getDirty()))) {
            return $save();
        }

        $dirty = $user->getDirty();
        $connection = $user->getConnection();
        $observed = $user->exists ? $connection->table('users')->where('id', $user->getKey())->first() : null;
        $scopeIds = array_values(array_unique(array_filter([
            $observed?->assigned_barangay_id, $dirty['assigned_barangay_id'] ?? $observed?->assigned_barangay_id,
        ])));
        sort($scopeIds);

        return $connection->transaction(function () use ($user, $save, $dirty, $connection, $scopeIds): bool {
            // Scope-before-account locking keeps competing account transitions in one lock order.
            $barangays = Barangay::on($user->getConnectionName())->withTrashed()
                ->whereIn('id', $scopeIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            // Merge the intended changes with locked current state, not a stale route-bound model.
            $stored = $user->exists
                ? $connection->table('users')->where('id', $user->getKey())->lockForUpdate()->first()
                : null;
            if ($stored?->assigned_barangay_id && ! in_array((int) $stored->assigned_barangay_id, array_map('intval', $scopeIds), true)) {
                throw ValidationException::withMessages(['assigned_barangay_id' => 'This account assignment changed while you were editing. Reload the account and try again.']);
            }
            $effective = array_replace((array) $stored, $dirty);
            $candidate = new User;
            $candidate->setRawAttributes($effective + ['is_active' => true, 'approval_status' => User::APPROVAL_APPROVED]);
            $barangay = $barangays->get($candidate->assigned_barangay_id);

            if ($barangay && $candidate->isOperationalSecretaryFor($barangay)) {
                $occupants = User::on($user->getConnectionName())->operationalSecretaries()
                    ->where('assigned_barangay_id', $barangay->id)
                    ->when($user->exists, fn ($query) => $query->whereKeyNot($user->getKey()))
                    ->lockForUpdate()->get(['id']);
                if ($occupants->isNotEmpty()) {
                    throw ValidationException::withMessages(['assigned_barangay_id' => self::MESSAGE]);
                }
            }

            if ($stored) {
                $user->setRawAttributes((array) $stored, true);
                $user->setRawAttributes($effective);
                $user->unsetRelation('assignedBarangay');
            }

            return $save();
        }, 5);
    }

    public function saveBarangay(Barangay $barangay, Closure $save): bool
    {
        if (! $barangay->exists || ! $barangay->isDirty(['is_active', 'deleted_at'])) {
            return $save();
        }

        $dirty = $barangay->getDirty();

        return $barangay->getConnection()->transaction(function () use ($barangay, $save, $dirty): bool {
            $stored = Barangay::on($barangay->getConnectionName())->withTrashed()->whereKey($barangay->id)->lockForUpdate()->firstOrFail();
            $effective = array_replace($stored->getAttributes(), $dirty);
            $candidate = new Barangay;
            $candidate->setRawAttributes($effective);
            if ($candidate->isActive()) {
                $candidates = User::on($barangay->getConnectionName())->where('assigned_barangay_id', $barangay->id)
                    ->where('role', 'secretary')->where('approval_status', User::APPROVAL_APPROVED)
                    ->where('is_active', true)->whereNotNull('email_verified_at')->lockForUpdate()->get(['id']);
                if ($candidates->count() > 1) {
                    throw ValidationException::withMessages(['is_active' => self::MESSAGE]);
                }
            }

            $barangay->setRawAttributes($stored->getAttributes(), true);
            $barangay->setRawAttributes($effective);

            return $save();
        }, 5);
    }
}
