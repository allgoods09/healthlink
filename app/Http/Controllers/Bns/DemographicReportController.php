<?php

namespace App\Http\Controllers\Bns;

use App\Http\Controllers\Bns\Concerns\InteractsWithBnsScope;
use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DemographicReportController extends Controller
{
    use InteractsWithBnsScope;

    public function index(Request $request): View
    {
        [$summary, $byPurok] = $this->reportData($request);

        return view('bns.reports.demographics', [
            'summary' => $summary,
            'byPurok' => $byPurok,
            'puroks' => $this->bnsPuroksQuery()->active()->orderBy('purok_number')->get(),
        ]);
    }

    public function export(Request $request, string $format)
    {
        [$summary, $byPurok] = $this->reportData($request);

        $columns = [
            'Purok' => 'purok',
            'Households' => 'households',
            'Residents' => 'residents',
            'Male' => 'male',
            'Female' => 'female',
            'Children (0-17)' => 'children',
            'Adults (18-59)' => 'adults',
            'Seniors (60+)' => 'seniors',
            'PWD Flags' => 'pwd',
            'Solo Parents' => 'solo_parents',
        ];

        $filters = [
            'Barangay' => $this->bnsUser()->assignedBarangay?->name,
            'Purok' => $this->bnsPuroksQuery()->find($request->integer('purok_id'))?->display_name,
        ];

        ExportAudit::log('bns demographic report', $format, [
            'model_type' => Resident::class,
            'record_count' => $byPurok->count(),
            'filters' => array_filter($filters),
        ]);

        $timestamp = now()->format('Y-m-d_His');

        return match ($format) {
            'csv' => TabularExport::csv("bns_demographics_{$timestamp}.csv", $columns, $byPurok),
            'xlsx' => TabularExport::xlsx("bns_demographics_{$timestamp}.xlsx", 'BNS Demographics', $columns, $byPurok),
            'pdf' => TabularExport::pdf("bns_demographics_{$timestamp}.pdf", 'Barangay Demographic Report', $columns, $byPurok, [
                ...$filters,
                'Total Residents' => $summary['residents'],
                'Total Households' => $summary['households'],
                'Children' => $summary['children'],
                'Adults' => $summary['adults'],
                'Seniors' => $summary['seniors'],
            ]),
            default => abort(404),
        };
    }

    private function reportData(Request $request): array
    {
        $residentQuery = $this->bnsResidentsQuery()->with(['household.purok', 'socioEconomicProfile']);
        $householdQuery = $this->bnsHouseholdsQuery()->with('purok');

        if ($request->filled('purok_id')) {
            $residentQuery->whereHas('household', function ($query) use ($request): void {
                $query->where('purok_id', $request->integer('purok_id'));
            });
            $householdQuery->where('purok_id', $request->integer('purok_id'));
        }

        $residents = $residentQuery->get();
        $households = $householdQuery->get();

        $summary = [
            'residents' => $residents->count(),
            'households' => $households->count(),
            'male' => $residents->where('sex', 'Male')->count(),
            'female' => $residents->where('sex', 'Female')->count(),
            'children' => $residents->filter(fn (Resident $resident) => $resident->age < 18)->count(),
            'adults' => $residents->filter(fn (Resident $resident) => $resident->age >= 18 && $resident->age < 60)->count(),
            'seniors' => $residents->filter(fn (Resident $resident) => $resident->age >= 60)->count(),
            'pwd' => $residents->filter(fn (Resident $resident) => (bool) $resident->socioEconomicProfile?->is_pwd)->count(),
            'solo_parents' => $residents->filter(fn (Resident $resident) => (bool) $resident->socioEconomicProfile?->is_solo_parent)->count(),
            'social_aid_households' => $households->where('is_social_aid_beneficiary', true)->count(),
        ];

        $byPurok = $this->bnsPuroksQuery()
            ->with([
                'households.residents.socioEconomicProfile',
            ])
            ->when($request->filled('purok_id'), fn ($query) => $query->whereKey($request->integer('purok_id')))
            ->orderBy('purok_number')
            ->get()
            ->map(function (Purok $purok): array {
                $households = $purok->households;
                $residents = $households->flatMap(fn (Household $household) => $household->residents);

                return [
                    'purok' => $purok->display_name,
                    'households' => $households->count(),
                    'residents' => $residents->count(),
                    'male' => $residents->where('sex', 'Male')->count(),
                    'female' => $residents->where('sex', 'Female')->count(),
                    'children' => $residents->filter(fn (Resident $resident) => $resident->age < 18)->count(),
                    'adults' => $residents->filter(fn (Resident $resident) => $resident->age >= 18 && $resident->age < 60)->count(),
                    'seniors' => $residents->filter(fn (Resident $resident) => $resident->age >= 60)->count(),
                    'pwd' => $residents->filter(fn (Resident $resident) => (bool) $resident->socioEconomicProfile?->is_pwd)->count(),
                    'solo_parents' => $residents->filter(fn (Resident $resident) => (bool) $resident->socioEconomicProfile?->is_solo_parent)->count(),
                ];
            });

        return [$summary, $byPurok];
    }
}
