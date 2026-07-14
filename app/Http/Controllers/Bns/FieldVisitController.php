<?php

namespace App\Http\Controllers\Bns;

use App\Http\Controllers\Bns\Concerns\InteractsWithBnsScope;
use App\Http\Controllers\Controller;
use App\Models\FieldVisit;
use App\Models\Purok;
use App\Models\User;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class FieldVisitController extends Controller
{
    use InteractsWithBnsScope;

    public function index(Request $request): View
    {
        $visits = $this->filteredQuery($request)
            ->with(['household.purok.barangay', 'recordedBy.assignedPurok'])
            ->latest('visited_at')
            ->paginate(20)
            ->withQueryString();

        return view('bns.visits.index', [
            'visits' => $visits,
            'bhws' => $this->bnsBhwsQuery(false)->active()->orderBy('name')->get(),
            'puroks' => $this->bnsPuroksQuery()->active()->orderBy('purok_number')->get(),
        ]);
    }

    public function show(FieldVisit $fieldVisit): View
    {
        $this->ensureFieldVisitBelongsToBarangay($fieldVisit);
        $fieldVisit->load(['household.purok.barangay', 'recordedBy.assignedPurok']);

        return view('bns.visits.show', [
            'visit' => $fieldVisit,
        ]);
    }

    public function export(Request $request, string $format)
    {
        $visits = $this->filteredQuery($request)
            ->with(['household.purok.barangay', 'recordedBy.assignedPurok'])
            ->latest('visited_at')
            ->get();

        $columns = [
            'Visited At' => fn (FieldVisit $visit) => $visit->visited_at?->format('Y-m-d H:i:s'),
            'BHW' => fn (FieldVisit $visit) => $visit->recordedBy?->name ?? 'Unknown',
            'Barangay' => fn (FieldVisit $visit) => $visit->household?->purok?->barangay?->name,
            'Purok' => fn (FieldVisit $visit) => $visit->household?->purok?->display_name,
            'Household' => fn (FieldVisit $visit) => $visit->household?->household_no,
            'Photos' => fn (FieldVisit $visit) => $visit->photo_count,
            'Source' => 'source',
            'Synced At' => fn (FieldVisit $visit) => $visit->last_synced_at?->format('Y-m-d H:i:s') ?? 'N/A',
            'Notes' => 'notes',
        ];

        $filters = [
            'Search' => $request->string('search')->toString(),
            'BHW' => User::find($request->integer('user_id'))?->name,
            'Purok' => Purok::find($request->integer('purok_id'))?->display_name,
            'Date From' => $request->date_from,
            'Date To' => $request->date_to,
        ];

        $timestamp = now()->format('Y-m-d_His');

        ExportAudit::log('bns field visits', $format, [
            'model_type' => FieldVisit::class,
            'record_count' => $visits->count(),
            'filters' => array_filter($filters),
        ]);

        return match ($format) {
            'csv' => TabularExport::csv("bns_field_visits_{$timestamp}.csv", $columns, $visits),
            'xlsx' => TabularExport::xlsx("bns_field_visits_{$timestamp}.xlsx", 'BNS Field Visits', $columns, $visits),
            'pdf' => TabularExport::pdf("bns_field_visits_{$timestamp}.pdf", 'Barangay Field Visit Log', $columns, $visits, $filters),
            default => abort(404),
        };
    }

    public function photo(FieldVisit $fieldVisit, int $photoIndex)
    {
        $this->ensureFieldVisitBelongsToBarangay($fieldVisit);

        $photo = ($fieldVisit->photos ?? [])[$photoIndex] ?? null;
        $path = $photo['path'] ?? null;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $photo['mime_type'] ?? 'image/jpeg',
        ]);
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = $this->bnsFieldVisitsQuery();

        if ($request->filled('user_id')) {
            $query->where('recorded_by_user_id', $request->integer('user_id'));
        }

        if ($request->filled('purok_id')) {
            $query->whereHas('household', function (Builder $builder) use ($request): void {
                $builder->where('purok_id', $request->integer('purok_id'));
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('visited_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('visited_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('notes', 'like', "%{$search}%")
                    ->orWhereHas('household', function (Builder $householdQuery) use ($search): void {
                        $householdQuery->where('household_no', 'like', "%{$search}%")
                            ->orWhere('household_address', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }
}
