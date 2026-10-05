<?php

namespace App\Http\Controllers\Bhw;

use App\Http\Controllers\Bhw\Concerns\InteractsWithBhwScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bhw\StoreTriageRecordRequest;
use App\Http\Requests\Bhw\UpdateTriageRecordRequest;
use App\Models\AuditLog;
use App\Models\TriageRecord;
use App\Support\ExportDownload;
use App\Support\RoleNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class TriageController extends Controller
{
    use InteractsWithBhwScope;

    public function index(Request $request): View
    {
        return view('bhw.triage.index', [
            'triageRecords' => $this->listingQuery($request)->paginate(12)->withQueryString(),
            'todayCount' => $this->bhwTriageRecordsQuery()->whereDate('measured_at', now()->toDateString())->count(),
            'editableCount' => $this->bhwTriageRecordsQuery()->whereNull('consumed_at')->count(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $columns = [
            'Resident' => fn (TriageRecord $record) => $record->resident?->formal_name ?? 'Unknown',
            'Purok' => fn (TriageRecord $record) => $record->resident?->household?->purok?->display_name ?? 'Unknown',
            'Measured At' => fn (TriageRecord $record) => $record->measured_at?->format('Y-m-d H:i:s'),
            'Status' => fn (TriageRecord $record) => $record->triage_status_label,
            'Blood Pressure' => fn (TriageRecord $record) => $record->bp_systolic && $record->bp_diastolic ? "{$record->bp_systolic}/{$record->bp_diastolic}" : 'N/A',
            'Temperature' => fn (TriageRecord $record) => $record->temperature_celsius ? "{$record->temperature_celsius} C" : 'N/A',
            'Consumed By' => fn (TriageRecord $record) => $record->consumedBy?->name ?? 'Pending',
        ];

        return ExportDownload::make($format, 'BHW Triage Records', 'Field Records', 'bhw_triage', $columns, $this->listingQuery($request)->get(), ['Search' => $request->input('search'), 'Status' => $request->input('status')], $this->bhwUser()->assignedBarangay?->name, TriageRecord::class);
    }

    private function listingQuery(Request $request): Builder
    {
        $query = $this->bhwTriageRecordsQuery()
            ->with(['resident.household.purok', 'consumedBy'])
            ->latest('measured_at')
            ->latest('id');

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if ($status === 'editable') {
                $query->whereNull('consumed_at');
            } else {
                $query->where('triage_status', $status);
            }
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($triageQuery) use ($search): void {
                $triageQuery->where('triage_notes', 'like', "%{$search}%")
                    ->orWhereHas('resident', function ($residentQuery) use ($search): void {
                        $residentQuery->where('official_resident_code', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

    public function create(Request $request): View
    {
        $selectedResident = null;

        if ($request->filled('resident_id')) {
            $selectedResident = $this->bhwResidentsQuery()
                ->currentPopulation()
                ->with('household.purok')
                ->find($request->integer('resident_id'));
        }

        return view('bhw.triage.create', [
            'selectedResident' => $selectedResident,
            'residentOptions' => $this->bhwResidentsQuery()
                ->with('household.purok')
                ->currentPopulation()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function store(StoreTriageRecordRequest $request, RoleNotificationService $roleNotificationService): RedirectResponse
    {
        $resident = $this->bhwResidentsQuery()
            ->with('household.purok')
            ->findOrFail($request->integer('resident_id'));

        $triageRecord = TriageRecord::query()->create([
            'resident_id' => $resident->id,
            'household_id' => $resident->household_id,
            'barangay_id' => $this->assignedBarangayId(),
            'purok_id' => $resident->household->purok_id,
            'recorded_by_user_id' => Auth::id(),
            'triage_status' => TriageRecord::STATUS_PENDING,
            'measured_at' => $request->date('measured_at'),
            'bp_systolic' => $request->input('bp_systolic'),
            'bp_diastolic' => $request->input('bp_diastolic'),
            'heart_rate' => $request->input('heart_rate'),
            'temperature_celsius' => $request->input('temperature_celsius'),
            'respiratory_rate' => $request->input('respiratory_rate'),
            'blood_glucose_mg_dl' => $request->input('blood_glucose_mg_dl'),
            'triage_notes' => $request->input('triage_notes'),
        ]);

        AuditLog::logMutation('created', Auth::user(), $triageRecord);
        $roleNotificationService->notifyTriageSubmitted($triageRecord);

        return redirect()
            ->route('bhw.triage.show', $triageRecord)
            ->with('success', 'Clinic triage entry forwarded to the clinical review queue.');
    }

    public function show(TriageRecord $triageRecord): View
    {
        $this->ensureTriageRecordBelongsToBhw($triageRecord);

        $triageRecord->load(['resident.household.purok', 'consumedBy']);

        return view('bhw.triage.show', [
            'triageRecord' => $triageRecord,
            'isEditable' => $this->triageIsEditable($triageRecord),
        ]);
    }

    public function edit(TriageRecord $triageRecord): View
    {
        $this->ensureTriageRecordBelongsToBhw($triageRecord);

        if (! $this->triageIsEditable($triageRecord)) {
            abort(403, 'This triage record has already been consumed and can no longer be edited.');
        }

        $triageRecord->load('resident.household.purok');

        return view('bhw.triage.edit', [
            'triageRecord' => $triageRecord,
            'residentOptions' => $this->bhwResidentsQuery()
                ->with('household.purok')
                ->currentPopulation()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function update(UpdateTriageRecordRequest $request, TriageRecord $triageRecord): RedirectResponse
    {
        $this->ensureTriageRecordBelongsToBhw($triageRecord);

        if (! $this->triageIsEditable($triageRecord)) {
            abort(403, 'This triage record has already been consumed and can no longer be edited.');
        }

        $oldValues = $triageRecord->toArray();

        $triageRecord->update($request->validated());

        AuditLog::logMutation('updated', Auth::user(), $triageRecord, $oldValues, $triageRecord->fresh()->toArray());

        return redirect()
            ->route('bhw.triage.show', $triageRecord)
            ->with('success', 'Clinic triage entry updated successfully.');
    }
}
