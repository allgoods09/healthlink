<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\BarangayOfficial;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class BarangayOfficialsRegistry
{
    /**
     * Ensure the standard official slots exist for the barangay.
     */
    public function syncDefaults(Barangay $barangay): Collection
    {
        foreach (BarangayOfficial::defaults() as $definition) {
            BarangayOfficial::query()->updateOrCreate(
                [
                    'barangay_id' => $barangay->id,
                    'role_key' => $definition['role_key'],
                ],
                [
                    'official_title' => $definition['official_title'],
                    'display_order' => $definition['display_order'],
                    'is_active' => true,
                ]
            );
        }

        return $barangay->officials()->get();
    }

    /**
     * Get the officials keyed by role for quick lookup.
     */
    public function keyed(Barangay $barangay): Collection
    {
        return $this->syncDefaults($barangay)->keyBy('role_key');
    }

    public function secretaryName(Barangay $barangay): ?string
    {
        return $this->resolvedSecretaryName($barangay);
    }

    public function operationalSecretary(Barangay $barangay, bool $lock = false): ?User
    {
        $query = User::query()->operationalSecretaries()->where('assigned_barangay_id', $barangay->id);
        $users = ($lock ? $query->lockForUpdate() : $query)->get();
        if ($users->count() > 1) {
            throw ValidationException::withMessages(['barangay_secretary' => 'More than one operational Secretary is assigned to this barangay. Ask the municipal administrator to resolve the assignments.']);
        }

        return $users->isEmpty() ? null : $users->sole();
    }

    public function resolvedSecretaryName(Barangay $barangay): ?string
    {
        return $this->operationalSecretary($barangay)?->display_name
            ?? $barangay->officials()->where('role_key', BarangayOfficial::ROLE_BARANGAY_SECRETARY)->value('official_name');
    }

    public function secretaryPresentation(Barangay $barangay): array
    {
        $account = $this->operationalSecretary($barangay);

        return [
            'secretaryLinked' => $account !== null,
            'resolvedSecretaryName' => $account?->display_name
                ?? $barangay->officials()->where('role_key', BarangayOfficial::ROLE_BARANGAY_SECRETARY)->value('official_name'),
        ];
    }

    public function punongBarangayName(Barangay $barangay): ?string
    {
        return $this->keyed($barangay)->get(BarangayOfficial::ROLE_PUNONG_BARANGAY)?->official_name;
    }

    public function updateNames(Barangay $barangay, array $names): void
    {
        $barangay->getConnection()->transaction(function () use ($barangay, $names): void {
            $locked = Barangay::query()->whereKey($barangay->id)->lockForUpdate()->firstOrFail();
            $linked = $this->operationalSecretary($locked, true) !== null;
            $officials = $this->syncDefaults($locked)->keyBy('role_key');
            foreach (BarangayOfficial::defaults() as $definition) {
                // An omitted disabled field must remain safe even if the officeholder changes before save.
                if ($definition['role_key'] === BarangayOfficial::ROLE_BARANGAY_SECRETARY
                    && ($linked || ! array_key_exists($definition['role_key'], $names))) {
                    continue;
                }
                $official = $officials->get($definition['role_key']);
                $name = trim((string) ($names[$definition['role_key']] ?? ''));
                $oldValues = $official->toArray();
                $official->update(['official_name' => $name !== '' ? $name : null]);
                AuditLog::logMutation('updated', Auth::user(), $official, $oldValues, $official->fresh()->toArray());
            }
        }, 5);
    }

    public function editableNameInput(Barangay $barangay, array $names): array
    {
        if ($this->operationalSecretary($barangay) !== null) {
            unset($names[BarangayOfficial::ROLE_BARANGAY_SECRETARY]);
        }

        return $names;
    }
}
