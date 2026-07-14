<?php

namespace App\Http\Controllers\Phn;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Phn\Concerns\InteractsWithPhnScope;
use App\Models\Barangay;
use App\Models\ClinicalEncounter;
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
    use InteractsWithPhnScope;

    public function index(Request $request): View
    {
        $triageRecords = $this->filteredQuery($request)
            ->with([
                'resident.household.purok.barangay',
                'household.purok.barangay',
                'recordedBy.assignedPurok',
                'consumedBy',
                'clinicalEncounter',
            ])
            ->latest('measured_at')
            ->paginate(15)
            ->withQueryString();

        return view('phn.triage.index', [
            'triageRecords' => $triageRecords,
            'barangays' => $this->phnBarangaysQuery()->active()->orderBy('name')->get(),
            'bhwUsers' => User::query()->where('role', 'bhw')->orderBy('name')->get(),
            'pendingCount' => $this->phnPendingTriageRecordsQuery()->count(),
            'reviewedTodayCount' => $this->phnClinicalEncountersQuery()->whereDate('encountered_at', now()->toDateString())->count(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $triageRecords = $this->filteredQuery($request)
            ->with([
                'resident.household.purok.barangay',
                'household.purok.barangay',
                'recordedBy.assignedPurok',
                'consumedBy',
                'clinicalEncounter',
            ])
            ->latest('measured_at')
            ->get();

        $columns = [
            'Measured At' => fn (TriageRecord $triageRecord) => $triageRecord->measured_at?->format('Y-m-d H:i:s'),
            'Resident' => fn (TriageRecord $triageRecord) => $triageRecord->resident?->formal_name ?? 'Unknown',
            'Barangay' => fn (TriageRecord $triageRecord) => $triageRecord->resident?->household?->purok?->barangay?->name ?? 'N/A',
            'Purok' => fn (TriageRecord $triageRecord) => $triageRecord->purok?->display_name ?? 'N/A',
            'Recorded By' => fn (TriageRecord $triageRecord) => $triageRecord->recordedBy?->name ?? 'Unknown',
            'Status' => fn (TriageRecord $triageRecord) => $triageRecord->triage_status_label,
            'Blood Pressure' => fn (TriageRecord $triageRecord) => $triageRecord->bp_systolic && $triageRecord->bp_diastolic
                ? "{$triageRecord->bp_systolic}/{$triageRecord->bp_diastolic}"
                : 'N/A',
            'Temperature' => fn (TriageRecord $triageRecord) => $triageRecord->temperature_celsius ? "{$triageRecord->temperature_celsius} C" : 'N/A',
            'Heart Rate' => fn (TriageRecord $triageRecord) => $triageRecord->heart_rate ?: 'N/A',
            'Respiratory Rate' => fn (TriageRecord $triageRecord) => $triageRecord->respiratory_rate ?: 'N/A',
            'Blood Glucose' => fn (TriageRecord $triageRecord) => $triageRecord->blood_glucose_mg_dl ? "{$triageRecord->blood_glucose_mg_dl} mg/dL" : 'N/A',
            'Consumed By' => fn (TriageRecord $triageRecord) => $triageRecord->consumedBy?->name ?? 'Pending PHN review',
            'Linked Encounter' => fn (TriageRecord $triageRecord) => $triageRecord->clinicalEncounter?->id ? 'Encounter #'.$triageRecord->clinicalEncounter->id : 'None',
        ];

        $filters = [
            'Search' => $request->input('search'),
            'Barangay' => Barangay::query()->find($request->input('barangay_id'))?->name,
            'Status' => $request->input('status', 'pending'),
            'Recorded By' => User::query()->find($request->input('recorded_by_user_id'))?->name,
        ];

        ExportAudit::log('phn triage queue', $format, [
            'model_type' => TriageRecord::class,
            'record_count' => $triageRecords->count(),
            'filters' => array_filter($filters),
        ]);

        $timestamp = now()->format('Y-m-d_His');

        return match ($format) {
            'csv' => TabularExport::csv("phn_triage_queue_{$timestamp}.csv", $columns, $triageRecords),
            'xlsx' => TabularExport::xlsx("phn_triage_queue_{$timestamp}.xlsx", 'PHN Triage Queue', $columns, $triageRecords),
            'pdf' => TabularExport::pdf("phn_triage_queue_{$timestamp}.pdf", 'PHN Pending Triage Queue', $columns, $triageRecords, $filters),
            default => abort(404),
        };
    }

    public function show(TriageRecord $triageRecord): View
    {
        $this->ensureTriageRecordExists($triageRecord);

        $triageRecord->load([
            'resident.household.purok.barangay',
            'household.purok.barangay',
            'recordedBy.assignedPurok',
            'consumedBy',
            'clinicalEncounter',
            'resident.latestOptMeasurement.campaignPeriod',
            'resident.nutritionFlags' => fn ($query) => $query->latest('flagged_at')->limit(5),
        ]);

        return view('phn.triage.show', [
            'triageRecord' => $triageRecord,
        ]);
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = $this->phnTriageRecordsQuery();
        $status = $request->string('status')->toString() ?: 'pending';

        match ($status) {
            'reviewed' => $query->where('triage_status', TriageRecord::STATUS_REVIEWED),
            'closed' => $query->where('triage_status', TriageRecord::STATUS_CLOSED),
            'all' => null,
            default => $query->where('triage_status', TriageRecord::STATUS_PENDING)->whereNull('consumed_at'),
        };

        if ($request->filled('barangay_id')) {
            $query->where('barangay_id', $request->integer('barangay_id'));
        }

        if ($request->filled('recorded_by_user_id')) {
            $query->where('recorded_by_user_id', $request->integer('recorded_by_user_id'));
        }

        if ($request->filled('resident_id')) {
            $query->where('resident_id', $request->integer('resident_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('measured_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('measured_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('triage_notes', 'like', "%{$search}%")
                    ->orWhereHas('resident', function (Builder $residentQuery) use ($search): void {
                        $residentQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('official_resident_code', 'like', "%{$search}%")
                            ->orWhere('philsys_card_no', 'like', "%{$search}%");
                    })
                    ->orWhereHas('household', function (Builder $householdQuery) use ($search): void {
                        $householdQuery->where('household_no', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }
}
