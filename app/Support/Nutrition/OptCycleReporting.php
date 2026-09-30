<?php

namespace App\Support\Nutrition;

use App\Models\OptCycle;

class OptCycleReporting
{
    // One latest cycle per barangay; do not mix old rounds into the denominator.
    public static function latestSummary(?int $barangayId = null): array
    {
        $cycles = OptCycle::query()->withProgress()->when($barangayId, fn ($q) => $q->where('barangay_id', $barangayId))
            ->whereRaw('id = (select c.id from opt_cycles as c where c.barangay_id = opt_cycles.barangay_id order by c.reference_date desc, c.id desc limit 1)')->get();
        $eligible = (int) $cycles->sum('entries_count');
        $measured = (int) $cycles->sum('measured_count');

        return ['cycles' => $cycles->count(), 'eligible' => $eligible, 'measured' => $measured,
            'unmeasured' => $eligible - $measured, 'coverage' => $eligible ? round($measured / $eligible * 100, 1) : 0];
    }
}
