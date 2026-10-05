<?php

namespace App\Support;

use App\Models\Household;
use App\Models\Resident;
use Illuminate\Validation\ValidationException;

class CurrentRbiEligibility
{
    public static function ensureResident(Resident $resident): void
    {
        if (! $resident->isCurrentPopulation()) {
            self::reject('resident', 'RBI Form B may only be generated for current residents.');
        }
    }

    public static function ensureHousehold(Household $household): void
    {
        if ($household->trashed() || $household->isVacant()) {
            self::reject('household', 'This household currently has no registered members.');
        }
    }

    private static function reject(string $field, string $message): never
    {
        if (request()->hasSession() && ! request()->expectsJson()) {
            request()->session()->flash('error', $message);
        }

        throw ValidationException::withMessages([$field => $message]);
    }
}
