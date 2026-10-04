<?php

namespace App\Http\Controllers\Bhw;

use App\Http\Controllers\Bhw\Concerns\InteractsWithBhwScope;
use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\FieldVisit;
use App\Support\ExportDownload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class HouseholdController extends Controller
{
    use InteractsWithBhwScope;

    public function index(Request $request): View
    {
        $query = $this->listingQuery($request);

        return view('bhw.households.index', [
            'households' => $query->paginate(15)->withQueryString(),
            'puroks' => $this->bhwPuroksQuery()->orderBy('purok_number')->get(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $columns = [
            'Household Code' => 'official_household_code',
            'Household No.' => 'household_no',
            'Purok' => fn (Household $household) => $household->purok?->display_name,
            'Address' => 'household_address',
            'Head of Household' => fn (Household $household) => $household->currentHeadResident()?->formal_name ?? 'Unassigned',
            'Residents' => 'current_members_count',
            'Social Aid' => fn (Household $household) => $household->is_social_aid_beneficiary ? 'Yes' : 'No',
            'Status' => fn (Household $household) => $household->is_active ? 'Active' : 'Inactive',
        ];
        $filters = [
            'Search' => $request->input('search'),
            'Purok' => $this->bhwPuroksQuery()->find($request->integer('purok_id'))?->display_name,
        ];

        return ExportDownload::make($format, 'BHW Households Directory', 'Community Records', 'bhw_households', $columns, $this->listingQuery($request)->get(), $filters, $this->bhwUser()->assignedBarangay?->name, Household::class);
    }

    private function listingQuery(Request $request): Builder
    {
        $query = $this->bhwHouseholdsQuery()
            ->with(['purok', 'headResident'])
            ->withCount('currentMembers')
            ->latest('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($householdQuery) use ($search): void {
                $householdQuery->where('official_household_code', 'like', "%{$search}%")
                    ->orWhere('household_no', 'like', "%{$search}%")
                    ->orWhere('household_address', 'like', "%{$search}%")
                    ->orWhereHas('headResident', function ($residentQuery) use ($search): void {
                        $residentQuery->currentPopulation()->whereColumn('residents.household_id', 'households.id');
                        $residentQuery->where(function ($names) use ($search): void {
                            $names->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    });
            });
        }

        if ($request->filled('purok_id')) {
            $query->where('purok_id', $request->integer('purok_id'));
        }

        return $query;
    }

    public function show(Household $household): View
    {
        $this->ensureHouseholdBelongsToBarangay($household);

        $household->load(['purok.barangay', 'headResident', 'residents', 'currentMembers']);

        return view('bhw.households.show', [
            'household' => $household,
            'visitHistory' => Gate::allows('viewHousehold', [FieldVisit::class, $household])
                ? $household->fieldVisits()->with('recordedBy')
                    ->orderByDesc('visited_at')->orderByDesc('id')
                    ->paginate(10, ['*'], 'visits_page')
                : null,
            'recentTriage' => $this->bhwTriageRecordsQuery()
                ->where('household_id', $household->id)
                ->latest('measured_at')
                ->limit(5)
                ->get(),
        ]);
    }
}
