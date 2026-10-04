<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Secretary\Concerns\InteractsWithSecretaryScope;
use App\Models\Resident;
use App\Support\ExportDownload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DemographicReportController extends Controller
{
    use InteractsWithSecretaryScope;

    public function index(Request $request): View
    {
        $residents = $this->listingQuery($request)
            ->paginate(20)
            ->withQueryString();

        $residentCollection = $this->listingQuery($request)->get();

        return view('secretary.reports.demographics', [
            'summary' => $this->buildSummary($residentCollection),
            'byPurok' => $this->buildPurokBreakdown($residentCollection),
            'residents' => $residents,
            'puroks' => $this->secretaryPuroksQuery()->active()->orderBy('purok_number')->get(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $residents = $this->listingQuery($request)->get();

        $columns = [
            'Resident' => fn (Resident $resident) => $resident->formal_name,
            'Sex' => 'sex',
            'Age' => 'age',
            'Birth Date' => fn (Resident $resident) => optional($resident->birth_date)?->format('Y-m-d'),
            'Purok' => fn (Resident $resident) => $resident->household?->purok?->display_name,
            'Household' => fn (Resident $resident) => $resident->household?->household_no,
            'Relationship to Household Head' => 'relationship_to_head',
            'Availability' => fn (Resident $resident) => $resident->is_active ? 'Active' : 'Inactive',
            'Civil Registry Status' => fn (Resident $resident) => $resident->resident_status_label,
            'Occupation' => fn (Resident $resident) => $resident->socioEconomicProfile?->occupation ?: 'N/A',
        ];

        $filters = [
            'Barangay' => $this->secretaryUser()->assignedBarangay?->name,
            'Purok' => $this->secretaryPuroksQuery()->find($request->integer('purok_id'))?->display_name,
            'Sex' => $request->input('sex'),
            'Age Group' => match ($request->input('age_group')) {
                'minor' => 'Minors (0-17)',
                'adult' => 'Adults (18-59)',
                'senior' => 'Seniors (60+)',
                default => null,
            },
            'Civil Registry Status' => match ($request->input('resident_status')) {
                Resident::STATUS_ACTIVE => 'Active Resident',
                Resident::STATUS_DECEASED => 'Deceased',
                Resident::STATUS_RELOCATED => 'Relocated',
                default => null,
            },
            'Availability' => $request->input('status'),
        ];

        if ($request->input('dataset', 'roster') === 'breakdown') {
            $breakdownColumns = [
                'Purok' => 'purok',
                'Households' => 'households',
                'Residents' => 'residents',
                'Active' => 'active',
                'Deceased' => 'deceased',
                'Relocated' => 'relocated',
                'Minors' => 'minors',
                'Seniors' => 'seniors',
            ];

            return ExportDownload::make($format, 'Barangay Demographics by Purok', 'Demographics', 'secretary_demographics_by_purok', $breakdownColumns, $this->buildPurokBreakdown($residents)->values(), $filters, $this->secretaryUser()->assignedBarangay?->name, Resident::class);
        }

        abort_unless($request->input('dataset', 'roster') === 'roster', 404);

        return ExportDownload::make($format, 'Barangay Demographic Roster', 'Demographics', 'secretary_demographic_roster', $columns, $residents, $filters, $this->secretaryUser()->assignedBarangay?->name, Resident::class, array_intersect_key($columns, array_flip(['Resident', 'Sex', 'Age', 'Purok', 'Household', 'Relationship to Household Head', 'Civil Registry Status'])));
    }

    private function listingQuery(Request $request): Builder
    {
        return $this->filteredResidentsQuery($request)
            ->with(['household.purok', 'socioEconomicProfile'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id');
    }

    private function filteredResidentsQuery(Request $request): Builder
    {
        $query = $this->secretaryResidentsQuery();

        if ($request->filled('purok_id')) {
            $query->whereHas('household', function (Builder $builder) use ($request): void {
                $builder->where('purok_id', $request->integer('purok_id'));
            });
        }

        if ($request->filled('sex')) {
            $query->where('sex', $request->input('sex'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        if ($request->filled('resident_status')) {
            $query->where('resident_status', $request->input('resident_status'));
        }

        if ($request->filled('age_group')) {
            match ($request->input('age_group')) {
                'minor' => $query->whereDate('birth_date', '>', now()->subYears(18)),
                'adult' => $query
                    ->whereDate('birth_date', '<=', now()->subYears(18))
                    ->whereDate('birth_date', '>', now()->subYears(60)),
                'senior' => $query->whereDate('birth_date', '<=', now()->subYears(60)),
                default => null,
            };
        }

        return $query;
    }

    private function buildSummary(Collection $residents): array
    {
        return [
            'residents' => $residents->count(),
            'households' => $residents->pluck('household_id')->filter()->unique()->count(),
            'male' => $residents->where('sex', 'Male')->count(),
            'female' => $residents->where('sex', 'Female')->count(),
            'active' => $residents->where('resident_status', Resident::STATUS_ACTIVE)->count(),
            'deceased' => $residents->where('resident_status', Resident::STATUS_DECEASED)->count(),
            'relocated' => $residents->where('resident_status', Resident::STATUS_RELOCATED)->count(),
            'minors' => $residents->filter(fn (Resident $resident) => $resident->age < 18)->count(),
            'adults' => $residents->filter(fn (Resident $resident) => $resident->age >= 18 && $resident->age < 60)->count(),
            'seniors' => $residents->filter(fn (Resident $resident) => $resident->age >= 60)->count(),
        ];
    }

    private function buildPurokBreakdown(Collection $residents): Collection
    {
        return $residents
            ->groupBy(fn (Resident $resident) => $resident->household?->purok?->id)
            ->filter()
            ->map(function (Collection $group): array {
                /** @var Resident $sample */
                $sample = $group->first();
                $purok = $sample->household?->purok;

                return [
                    'sort_number' => $purok?->purok_number,
                    'purok' => $purok?->display_name ?? 'Unknown Purok',
                    'households' => $group->pluck('household_id')->filter()->unique()->count(),
                    'residents' => $group->count(),
                    'male' => $group->where('sex', 'Male')->count(),
                    'female' => $group->where('sex', 'Female')->count(),
                    'active' => $group->where('resident_status', Resident::STATUS_ACTIVE)->count(),
                    'deceased' => $group->where('resident_status', Resident::STATUS_DECEASED)->count(),
                    'relocated' => $group->where('resident_status', Resident::STATUS_RELOCATED)->count(),
                    'minors' => $group->filter(fn (Resident $resident) => $resident->age < 18)->count(),
                    'seniors' => $group->filter(fn (Resident $resident) => $resident->age >= 60)->count(),
                ];
            })
            ->sortBy('sort_number')
            ->values();
    }
}
