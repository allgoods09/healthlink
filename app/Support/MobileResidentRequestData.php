<?php

namespace App\Support;

use App\Models\Resident;
use Illuminate\Validation\ValidationException;

final class MobileResidentRequestData
{
    public const EDITABLE = ['household_id', 'last_name', 'first_name', 'middle_name', 'suffix',
        'birth_date', 'birth_place', 'sex', 'civil_status', 'citizenship', 'religion',
        'contact_number', 'email_address', 'relationship_to_head'];

    public static function snapshot(Resident $resident): array
    {
        $snapshot = [];
        foreach ([...self::EDITABLE, 'philsys_card_no', 'is_active', 'resident_status', 'deleted_at'] as $field) {
            $value = $resident->{$field};
            $snapshot[$field] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
        }
        return $snapshot;
    }

    public static function assertUnchanged(Resident $resident, array $base): void
    {
        $current = self::snapshot($resident);
        foreach ($base as $field => $value) {
            // Older captured snapshots serialized DOB as an ISO date-time.
            if ($field === 'birth_date' && is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T/', $value)) {
                $value = substr($value, 0, 10);
            }
            if (array_key_exists($field, $current) && (string) $current[$field] !== (string) $value) {
                throw ValidationException::withMessages(['resident' =>
                    'The official resident record changed. Reload and review the correction before submitting or approving it.']);
            }
        }
        if (! $resident->isCurrentPopulation()) {
            throw ValidationException::withMessages(['resident' => 'This resident is no longer eligible for a current profile correction.']);
        }
    }
}
