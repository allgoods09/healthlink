<?php

namespace App\Http\Controllers\Bns;

use App\Http\Controllers\Bns\Concerns\InteractsWithBnsScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bns\StoreInfantFeedingLogRequest;
use App\Http\Requests\Bns\StoreMaternalHistoryRequest;
use App\Http\Requests\Bns\UpsertMaternalProfileRequest;
use App\Models\AuditLog;
use App\Models\InfantFeedingLog;
use App\Models\MaternalNutritionHistory;
use App\Models\MaternalNutritionProfile;
use App\Models\Resident;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MaternalTrackingController extends Controller
{
    use InteractsWithBnsScope;

    public function index(Request $request): View
    {
        $query = $this->bnsMaternalProfilesQuery()
            ->with(['resident.household.purok', 'updatedBy'])
            ->latest('last_status_updated_at')
            ->latest('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->whereHas('resident', function ($residentQuery) use ($search): void {
                $residentQuery->where('official_resident_code', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('current_status')) {
            $status = $request->string('current_status')->toString();
            if ($status === 'pregnant') {
                $query->where('is_currently_pregnant', true);
            }
            if ($status === 'lactating') {
                $query->where('is_currently_lactating', true);
            }
        }

        $recentHistories = MaternalNutritionHistory::query()
            ->whereHas('resident.household.purok', function ($builder): void {
                $builder->where('barangay_id', $this->assignedBarangayId());
            })
            ->with(['resident.household.purok', 'recordedBy'])
            ->latest('event_date')
            ->latest('id')
            ->limit(8)
            ->get();

        return view('bns.maternal.index', [
            'profiles' => $query->paginate(12)->withQueryString(),
            'femaleResidents' => $this->bnsMaternalEligibleResidentsQuery()
                ->with('household.purok')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'pregnantCount' => $this->bnsMaternalProfilesQuery()->where('is_currently_pregnant', true)->count(),
            'lactatingCount' => $this->bnsMaternalProfilesQuery()->where('is_currently_lactating', true)->count(),
            'recentHistories' => $recentHistories,
        ]);
    }

    public function show(Resident $resident): View
    {
        $this->ensureResidentBelongsToBarangay($resident);

        if ($resident->sex !== 'Female') {
            abort(404);
        }

        $resident->load(['household.purok', 'maternalNutritionProfile.updatedBy']);
        $profile = $resident->maternalNutritionProfile ?? new MaternalNutritionProfile([
            'resident_id' => $resident->id,
            'barangay_id' => $this->assignedBarangayId(),
            'is_currently_pregnant' => false,
            'is_currently_lactating' => false,
        ]);

        $histories = $resident->maternalNutritionHistories()
            ->with('recordedBy')
            ->latest('event_date')
            ->latest('id')
            ->get();

        $infantFeedingLogs = InfantFeedingLog::query()
            ->where('mother_resident_id', $resident->id)
            ->with(['resident.household.purok', 'recordedBy'])
            ->latest('observed_on')
            ->latest('id')
            ->get();

        return view('bns.maternal.show', [
            'resident' => $resident,
            'profile' => $profile,
            'histories' => $histories,
            'infantFeedingLogs' => $infantFeedingLogs,
            'historyEventTypes' => MaternalNutritionHistory::EVENT_TYPES,
            'feedingMethods' => InfantFeedingLog::METHODS,
            'infantResidents' => $this->bnsInfantEligibleResidentsQuery()
                ->with('household.purok')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function upsertProfile(UpsertMaternalProfileRequest $request, ?Resident $resident = null): RedirectResponse
    {
        if (! $resident) {
            $resident = $this->bnsMaternalEligibleResidentsQuery()
                ->findOrFail($request->integer('resident_id'));
        } else {
            $this->ensureResidentBelongsToBarangay($resident);
        }

        $oldProfile = $resident->maternalNutritionProfile;

        $profile = DB::transaction(function () use ($request, $resident, $oldProfile): MaternalNutritionProfile {
            $profile = MaternalNutritionProfile::query()->updateOrCreate(
                ['resident_id' => $resident->id],
                [
                    'barangay_id' => $this->assignedBarangayId(),
                    'updated_by_user_id' => Auth::id(),
                    'is_currently_pregnant' => $request->boolean('is_currently_pregnant'),
                    'is_currently_lactating' => $request->boolean('is_currently_lactating'),
                    'expected_delivery_date' => $request->input('expected_delivery_date'),
                    'current_risk_notes' => $request->input('current_risk_notes'),
                    'last_status_updated_at' => now(),
                ]
            );

            $changes = [
                'is_currently_pregnant' => [
                    'old' => $oldProfile?->is_currently_pregnant,
                    'new' => $profile->is_currently_pregnant,
                    'event' => MaternalNutritionHistory::EVENT_PREGNANCY_STATUS_CHANGE,
                ],
                'is_currently_lactating' => [
                    'old' => $oldProfile?->is_currently_lactating,
                    'new' => $profile->is_currently_lactating,
                    'event' => MaternalNutritionHistory::EVENT_LACTATING_STATUS_CHANGE,
                ],
            ];

            foreach ($changes as $change) {
                if ($change['old'] === null && ! $change['new']) {
                    continue;
                }

                if ($change['old'] !== null && (bool) $change['old'] === (bool) $change['new']) {
                    continue;
                }

                MaternalNutritionHistory::query()->create([
                    'resident_id' => $resident->id,
                    'recorded_by_user_id' => Auth::id(),
                    'event_type' => $change['event'],
                    'event_date' => Carbon::today(),
                    'notes' => $change['new'] ? 'Status marked active in maternal tracking.' : 'Status marked inactive in maternal tracking.',
                ]);
            }

            return $profile;
        });

        if ($oldProfile) {
            AuditLog::logMutation('updated', Auth::user(), $profile, $oldProfile->toArray(), $profile->fresh()->toArray());
        } else {
            AuditLog::logMutation('created', Auth::user(), $profile);
        }

        return redirect()
            ->route('bns.maternal.show', $resident)
            ->with('success', 'Maternal nutrition profile saved successfully.');
    }

    public function storeHistory(StoreMaternalHistoryRequest $request, Resident $resident): RedirectResponse
    {
        $this->ensureResidentBelongsToBarangay($resident);

        $history = MaternalNutritionHistory::query()->create([
            ...$request->validated(),
            'resident_id' => $resident->id,
            'recorded_by_user_id' => Auth::id(),
        ]);

        AuditLog::logMutation('created', Auth::user(), $history);

        return redirect()
            ->route('bns.maternal.show', $resident)
            ->with('success', 'Maternal history entry recorded successfully.');
    }

    public function storeInfantFeedingLog(StoreInfantFeedingLogRequest $request, Resident $resident): RedirectResponse
    {
        $this->ensureResidentBelongsToBarangay($resident);

        $feedingLog = InfantFeedingLog::query()->create([
            ...$request->validated(),
            'mother_resident_id' => $resident->id,
            'recorded_by_user_id' => Auth::id(),
        ]);

        AuditLog::logMutation('created', Auth::user(), $feedingLog);

        return redirect()
            ->route('bns.maternal.show', $resident)
            ->with('success', 'Infant feeding log recorded successfully.');
    }
}
