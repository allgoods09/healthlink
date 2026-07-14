<?php

namespace App\Http\Controllers\Bhw;

use App\Http\Controllers\Bhw\Concerns\InteractsWithBhwScope;
use App\Http\Controllers\Controller;
use App\Models\Household;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HouseholdController extends Controller
{
    use InteractsWithBhwScope;

    public function index(Request $request): View
    {
        $query = $this->bhwHouseholdsQuery()
            ->with(['purok', 'headResident'])
            ->withCount('residents')
            ->latest('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($householdQuery) use ($search): void {
                $householdQuery->where('official_household_code', 'like', "%{$search}%")
                    ->orWhere('household_no', 'like', "%{$search}%")
                    ->orWhere('household_address', 'like', "%{$search}%")
                    ->orWhereHas('headResident', function ($residentQuery) use ($search): void {
                        $residentQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('purok_id')) {
            $query->where('purok_id', $request->integer('purok_id'));
        }

        return view('bhw.households.index', [
            'households' => $query->paginate(15)->withQueryString(),
            'puroks' => $this->bhwPuroksQuery()->orderBy('purok_number')->get(),
        ]);
    }

    public function show(Household $household): View
    {
        $this->ensureHouseholdBelongsToBarangay($household);

        $household->load(['purok', 'headResident', 'residents']);

        return view('bhw.households.show', [
            'household' => $household,
            'recentTriage' => $this->bhwTriageRecordsQuery()
                ->where('household_id', $household->id)
                ->latest('measured_at')
                ->limit(5)
                ->get(),
        ]);
    }
}
