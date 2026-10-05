<?php

namespace App\Support;

use App\Models\Resident;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Web directory modes only; historical relationships and mobile keep their own scope. */
class WebResidentPopulation
{
    public static function directory(Builder $query, Request $request, bool $archive = false): Builder
    {
        if ($archive && $request->input('lifecycle') === 'deleted') {
            return $query->onlyTrashed();
        }

        $status = $request->input('resident_status') ?: ($request->input('lifecycle') === 'all' ? 'all' : 'active');

        return match ($status) {
            'all' => $query->withoutTrashed(),
            Resident::STATUS_DECEASED, Resident::STATUS_MOVED_OUT, Resident::STATUS_RELOCATED => $query->where('resident_status', $status),
            default => $query->currentPopulation(),
        };
    }

    public static function description(Request $request, bool $archive = false): string
    {
        if ($archive && $request->input('lifecycle') === 'deleted') {
            return 'Archived records';
        }

        return match ($request->input('resident_status') ?: ($request->input('lifecycle') === 'all' ? 'all' : 'active')) {
            'all' => 'All current and historical residents (excluding archived)',
            Resident::STATUS_DECEASED => 'Deceased',
            Resident::STATUS_MOVED_OUT => 'Moved Out',
            Resident::STATUS_RELOCATED => 'Relocated (Legacy)',
            default => 'Current / Active',
        };
    }

    public static function label(Resident $resident): string
    {
        return $resident->resident_status === Resident::STATUS_RELOCATED ? 'Relocated (Legacy)' : $resident->resident_status_label;
    }
}
