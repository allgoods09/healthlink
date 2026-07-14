<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Secretary\Concerns\InteractsWithSecretaryScope;
use App\Models\Purok;
use App\Models\Resident;
use App\Support\ExportAudit;
use App\Support\TabularExport;
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
        $residents = $this->filteredResidentsQuery($request)
            ->with(['household.purok', 'socioEconomicProfile'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        $residentCollection = $this->filteredResidentsQuery($request)
            ->with(['household.purok', 'socioEconomicProfile'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('secretary.reports.demographics', [
            'summary' => $this->buildSummary($residentCollection),
            'byPurok' => $this->buildPurokBreakdown($residentCollection),
            'residents' => $residents,
            'puroks' => $this->secretaryPuroksQuery()->active()->orderBy('purok_number')->get(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $residents = $this->filteredResidentsQuery($request)
            ->with(['household.purok', 'socioEconomicProfile'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $summary = $this->buildSummary($residents);

        $columns = [
            'Resident' => fn (Resident $resident) => $resident->formal_name,
            'Sex' => 'sex',
            'Age' => 'age',
            'Birth Date' => fn (Resident $resident) => optional($resident->birth_date)?->format('Y-m-d'),
            'Purok' => fn (Resident $resident) => $resident->household?->purok?->display_name,
            'Household' => fn (Resident $resident) => $resident->household?->household_no,
            'Relationship' => 'relationship_to_head',
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

        ExportAudit::log('secretary demographic roster', $format, [
            'model_type' => Resident::class,
            'record_count' => $residents->count(),
            'filters' => array_filter($filters),
        ]);

        $timestamp = now()->format('Y-m-d_His');

        return match ($format) {
            'csv' => TabularExport::csv("secretary_demographics_{$timestamp}.csv", $columns, $residents),
            'xlsx' => TabularExport::xlsx("secretary_demographics_{$timestamp}.xlsx", 'Secretary Demographics', $columns, $residents),
            'pdf' => TabularExport::pdf("secretary_demographics_{$timestamp}.pdf", 'Barangay Demographic Roster', $columns, $residents, [
                ...$filters,
                'Residents in Result' => $summary['residents'],
                'Households in Result' => $summary['households'],
                'Seniors in Result' => $summary['seniors'],
                'Minors in Result' => $summary['minors'],
            ]),
            default => abort(404),
        };
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
