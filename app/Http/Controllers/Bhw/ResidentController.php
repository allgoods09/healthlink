<?php

namespace App\Http\Controllers\Bhw;

use App\Http\Controllers\Bhw\Concerns\InteractsWithBhwScope;
use App\Http\Controllers\Controller;
use App\Models\Resident;
use App\Support\ExportDownload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ResidentController extends Controller
{
    use InteractsWithBhwScope;

    public function index(Request $request): View
    {
        $query = $this->listingQuery($request);

        return view('bhw.residents.index', [
            'residents' => $query->paginate(15)->withQueryString(),
            'puroks' => $this->bhwPuroksQuery()->orderBy('purok_number')->get(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $columns = [
            'Resident Code' => 'official_resident_code',
            'Resident' => fn (Resident $resident) => $resident->formal_name,
            'Sex' => 'sex',
            'Age' => 'age',
            'Purok' => fn (Resident $resident) => $resident->household?->purok?->display_name,
            'Household' => fn (Resident $resident) => $resident->household?->household_no,
            'Residency Status' => fn (Resident $resident) => $resident->resident_status_label,
        ];
        $filters = [
            'Search' => $request->input('search'),
            'Purok' => $this->bhwPuroksQuery()->find($request->integer('purok_id'))?->display_name,
            'Residency Status' => $request->input('resident_status'),
        ];

        return ExportDownload::make($format, 'BHW Residents Directory', 'Community Records', 'bhw_residents', $columns, $this->listingQuery($request)->get(), $filters, $this->bhwUser()->assignedBarangay?->name, Resident::class);
    }

    private function listingQuery(Request $request): Builder
    {
        $query = $this->bhwResidentsQuery()
            ->with(['household.purok'])
            ->latest('last_name')
            ->latest('first_name')
            ->latest('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($residentQuery) use ($search): void {
                $residentQuery->where('official_resident_code', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('contact_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('purok_id')) {
            $query->whereHas('household', fn ($householdQuery) => $householdQuery->where('purok_id', $request->integer('purok_id')));
        }

        if ($request->filled('resident_status')) {
            $query->where('resident_status', $request->string('resident_status')->toString());
        }

        return $query;
    }

    public function show(Resident $resident): View
    {
        $this->ensureResidentBelongsToBarangay($resident);

        $resident->load([
            'household.headResident',
            'household.purok',
            'latestOptMeasurement.campaignPeriod',
            'maternalNutritionProfile',
        ]);

        return view('bhw.residents.show', [
            'resident' => $resident,
            'recentTriage' => $this->bhwTriageRecordsQuery()
                ->where('resident_id', $resident->id)
                ->latest('measured_at')
                ->limit(5)
                ->get(),
            'openFlag' => $this->bhwOpenNutritionFlagsQuery()
                ->where('resident_id', $resident->id)
                ->latest('flagged_at')
                ->first(),
        ]);
    }
}
