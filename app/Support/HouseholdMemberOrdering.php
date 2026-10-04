<?php

namespace App\Support;

use App\Models\Household;
use App\Models\Resident;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HouseholdMemberOrdering
{
    /** Presentation only: retain every supplied member and never mutate the relationship or head FK. */
    public static function ordered(Household $household, ?Collection $members = null): Collection
    {
        return ($members ?? $household->residents)->sortBy(function (Resident $resident) use ($household) {
            $category = $household->head_resident_id && (int) $resident->id === (int) $household->head_resident_id
                ? 0 : HouseholdRelationships::presentationCategory($resident->relationship_to_head);
            $name = Str::lower(Str::ascii(preg_replace('/\s+/u', ' ', trim(
                $resident->last_name.' '.$resident->first_name.' '.$resident->middle_name.' '.$resident->suffix
            ))));

            return [$category, $category === 2 ? (self::birthDate($resident) ?? '9999-99-99') : '', $name, (int) $resident->id];
        })->values();
    }

    public static function birthDate(Resident $resident): ?string
    {
        // Read raw attributes so malformed legacy dates cannot make presentation sorting throw.
        $raw = $resident->getAttributes()['birth_date'] ?? null;
        if (! is_string($raw) || ! preg_match('/^\d{4}-\d{2}-\d{2}(?: 00:00:00)?$/', $raw)) {
            return null;
        }
        $date = substr($raw, 0, 10);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed && $parsed->format('Y-m-d') === $date && $date <= now()->toDateString() ? $date : null;
    }
}
