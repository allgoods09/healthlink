<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Secretary\Concerns\InteractsWithSecretaryScope;
use App\Models\Purok;
use App\Models\TriageRecord;
use App\Models\User;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class TriageQueueController extends Controller
{
    use InteractsWithSecretaryScope;

    public function index(Request $request): View
    {
        $triageRecords = $this->filteredQuery($request)
            ->with([
                'resident.household.purok',
                'household.purok',
                'recordedBy.assignedPurok',
                'consumedBy',
            ])
            ->latest('measured_at')
            ->paginate(15)
            ->withQueryString();

        return view('secretary.triage.index', [
            'triageRecords' => $triageRecords,
            'puroks' => $this->secretaryPuroksQuery()->active()->orderBy('purok_number')->get(),
            'frontlineUsers' => $this->secretaryFrontlineUsersQuery()
                ->where('role', 'bhw')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $triageRecords = $this->filteredQuery($request)
            ->with([
                'resident.household.purok',
                'household.purok',
                'recordedBy.assignedPurok',
                'consumedBy',
            ])
            ->latest('measured_at')
            ->get();

        $columns = [
            'Measured At' => fn (TriageRecord $triageRecord) => $triageRecord->measured_at?->format('Y-m-d H:i:s'),
            'Resident' => fn (TriageRecord $triageRecord) => $triageRecord->resident?->formal_name ?? 'Unknown',
            'Household' => fn (TriageRecord $triageRecord) => $triageRecord->household?->household_no ?? 'N/A',
            'Purok' => fn (TriageRecord $triageRecord) => $triageRecord->purok?->display_name ?? 'N/A',
            'Recorded By' => fn (TriageRecord $triageRecord) => $triageRecord->recordedBy?->name ?? 'Unknown',
            'Status' => fn (TriageRecord $triageRecord) => $triageRecord->triage_status_label,
            'Blood Pressure' => fn (TriageRecord $triageRecord) => $triageRecord->bp_systolic && $triageRecord->bp_diastolic
                ? "{$triageRecord->bp_systolic}/{$triageRecord->bp_diastolic}"
                : 'N/A',
            'Temperature' => fn (TriageRecord $triageRecord) => $triageRecord->temperature_celsius ? "{$triageRecord->temperature_celsius} C" : 'N/A',
            'Heart Rate' => fn (TriageRecord $triageRecord) => $triageRecord->heart_rate ?: 'N/A',
            'Blood Glucose' => fn (TriageRecord $triageRecord) => $triageRecord->blood_glucose_mg_dl ? "{$triageRecord->blood_glucose_mg_dl} mg/dL" : 'N/A',
            'Consumed By' => fn (TriageRecord $triageRecord) => $triageRecord->consumedBy?->name ?? 'Pending PHN/MHO review',
        ];

        $filters = [
            'Search' => $request->input('search'),
            'Purok' => Purok::query()->find($request->input('purok_id'))?->display_name,
            'Status' => $request->input('status'),
            'Recorded By' => User::query()->find($request->input('recorded_by_user_id'))?->name,
        ];

        ExportAudit::log('secretary triage queue', $format, [
            'model_type' => TriageRecord::class,
            'record_count' => $triageRecords->count(),
            'filters' => array_filter($filters),
        ]);

        $timestamp = now()->format('Y-m-d_His');

        return match ($format) {
            'csv' => TabularExport::csv("secretary_triage_queue_{$timestamp}.csv", $columns, $triageRecords),
            'xlsx' => TabularExport::xlsx("secretary_triage_queue_{$timestamp}.xlsx", 'Triage Queue', $columns, $triageRecords),
            'pdf' => TabularExport::pdf("secretary_triage_queue_{$timestamp}.pdf", 'Secretary Pending Triage Queue', $columns, $triageRecords, $filters),
            default => abort(404),
        };
    }

    public function show(TriageRecord $triageRecord): View
    {
        $this->ensureTriageRecordBelongsToBarangay($triageRecord);

        $triageRecord->load([
            'resident.household.purok',
            'household.purok',
            'recordedBy.assignedPurok',
            'consumedBy',
        ]);

        return view('secretary.triage.show', [
            'triageRecord' => $triageRecord,
        ]);
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = $this->secretaryTriageRecordsQuery();

        if ($request->filled('purok_id')) {
            $query->where('purok_id', $request->integer('purok_id'));
        }

        if ($request->filled('recorded_by_user_id')) {
            $query->where('recorded_by_user_id', $request->integer('recorded_by_user_id'));
        }

        if ($request->filled('status')) {
            $query->where('triage_status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('measured_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('measured_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('triage_notes', 'like', "%{$search}%")
                    ->orWhereHas('resident', function (Builder $residentQuery) use ($search): void {
                        $residentQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('household', function (Builder $householdQuery) use ($search): void {
                        $householdQuery->where('household_no', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }
}
