<?php

namespace App\Support;

use App\Models\Household;
use Illuminate\Validation\ValidationException;

final class MobileHouseholdRequestData
{
    public const VERSION = 1;

    public const EDITABLE = ['household_no', 'household_address', 'is_social_aid_beneficiary'];

    public static function snapshot(Household $household): array
    {
        return [
            'purok_id' => (int) $household->purok_id,
            'household_no' => $household->household_no,
            'household_address' => $household->household_address,
            'is_social_aid_beneficiary' => (bool) $household->is_social_aid_beneficiary,
        ];
    }

    public static function assertUnchanged(Household $household, array $base, array $fields): void
    {
        $current = self::snapshot($household);
        foreach (['purok_id', ...$fields] as $field) {
            if (! array_key_exists($field, $base) || ! array_key_exists($field, $current) ||
                (string) $current[$field] !== (string) $base[$field]) {
                throw ValidationException::withMessages(['household' =>
                    'The official household changed. Reload and review the correction before submitting or approving it.']);
            }
        }
    }
}
