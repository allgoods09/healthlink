<?php

namespace App\Support;

use App\Models\Resident;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HouseholdRelationships
{
    public const HEAD = 'Head of Household';

    public static function groups(): array
    {
        return [
            'Core Household' => ['Spouse / Partner'],
            'Children' => ['Son', 'Daughter', 'Stepson', 'Stepdaughter', 'Son-in-law', 'Daughter-in-law'],
            'Ascendants / Descendants' => ['Father', 'Mother', 'Father-in-law', 'Mother-in-law', 'Grandson', 'Granddaughter'],
            'Other Relatives' => ['Brother', 'Sister', 'Brother-in-law', 'Sister-in-law', 'Uncle', 'Aunt', 'Nephew', 'Niece', 'Cousin', 'Other Relative'],
            'Non-Relatives' => ['Domestic Helper / Kasambahay', 'Boarder / Lodger', 'Foster Child', 'Friend', 'Other Non-Relative'],
        ];
    }

    public static function choices(): array
    {
        return array_merge(...array_values(self::groups()));
    }

    public static function presentationCategory(?string $value): int
    {
        $value = mb_strtolower(trim((string) $value));
        if (in_array($value, ['spouse / partner', 'spouse'], true)) {
            return 1;
        }
        if (in_array($value, ['son', 'daughter', 'stepson', 'stepdaughter', 'child'], true)) {
            return 2;
        }
        $groups = self::groups();
        $relatives = [...$groups['Ascendants / Descendants'], ...$groups['Other Relatives'],
            'Son-in-law', 'Daughter-in-law', 'Parent', 'Grandchild', 'Sibling'];
        if (in_array($value, array_map('mb_strtolower', $relatives), true)) {
            return 3;
        }
        if (in_array($value, array_map('mb_strtolower', $groups['Non-Relatives']), true)) {
            return 4;
        }

        return 5;
    }

    public static function isHead(?string $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['head', 'head of household', 'household head'], true);
    }

    public static function validate(mixed $value, ?string $current, string $field = 'relationship_to_head', bool $review = false): string
    {
        if (! is_string($value) || trim($value) === '' || mb_strlen($value) > 100 ||
            self::isHead($value) && ($review || $value !== $current) ||
            ! in_array($value, self::choices(), true) && $value !== $current) {
            throw ValidationException::withMessages([$field => 'Choose a relationship to the household head. An unchanged current value may be retained.']);
        }

        return $value;
    }

    public static function prepareResidentRequest(Request $request): void
    {
        $resident = $request->route('resident');
        $retainingHead = $resident instanceof Resident && $resident->is_household_head &&
            (int) $request->input('household_id') === (int) $resident->household_id;

        if ($request->boolean('set_as_household_head') || $retainingHead) {
            $request->merge(['relationship_to_head' => self::HEAD]);
        }
    }
}
