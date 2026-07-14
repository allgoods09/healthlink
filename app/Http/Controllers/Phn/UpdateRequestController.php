<?php

namespace App\Http\Controllers\Phn;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Phn\Concerns\InteractsWithPhnScope;
use App\Http\Requests\Phn\StoreHouseholdUpdateRequest;
use App\Http\Requests\Phn\StoreResidentUpdateRequest;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\ProfileUpdateRequest;
use App\Models\Resident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UpdateRequestController extends Controller
{
    use InteractsWithPhnScope;

    public function index(Request $request): View
    {
        $query = $this->phnOwnProfileUpdateRequestsQuery()
            ->with(['resident.household.purok', 'household.purok', 'reviewedBy'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('request_status', $request->string('status')->toString());
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->string('subject_type')->toString());
        }

        return view('bhw.update-requests.index', [
            'routePrefix' => 'phn',
            'pageTitle' => 'PHN Correction Requests - HealthLink',
            'pageHeader' => 'Correction Requests',
            'pageSubheader' => 'Route barangay registry corrections back to the Secretary queue from the municipal clinical workspace.',
            'updateRequests' => $query->paginate(12)->withQueryString(),
        ]);
    }

    public function createResident(Request $request): View
    {
        $selectedResident = null;

        if ($request->filled('resident_id')) {
            $selectedResident = $this->phnResidentsQuery()
                ->with('household.purok')
                ->find($request->integer('resident_id'));
        }

        return view('bhw.update-requests.create-resident', [
            'routePrefix' => 'phn',
            'pageTitle' => 'PHN Resident Correction Request - HealthLink',
            'pageHeader' => 'Resident Correction Request',
            'pageSubheader' => 'Submit verified resident corrections to the assigned Barangay Secretary after RHU review.',
            'selectedResident' => $selectedResident,
            'residentOptions' => $this->phnResidentsQuery()
                ->with('household.purok')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'householdOptions' => $this->phnHouseholdsQuery()
                ->with(['purok', 'headResident'])
                ->orderBy('household_no')
                ->get(),
        ]);
    }

    public function storeResident(StoreResidentUpdateRequest $request): RedirectResponse
    {
        $resident = $this->phnResidentsQuery()
            ->with('household.purok')
            ->findOrFail($request->integer('subject_id'));

        $updateRequest = ProfileUpdateRequest::query()->create([
            'submitted_by_user_id' => Auth::id(),
            'barangay_id' => $resident->household->purok->barangay_id,
            'subject_type' => ProfileUpdateRequest::SUBJECT_RESIDENT,
            'subject_id' => $resident->id,
            'current_snapshot' => $this->residentSnapshot($resident),
            'proposed_changes' => Arr::except($request->validated(), ['subject_id', 'request_reason']),
            'request_reason' => $request->string('request_reason')->toString(),
            'request_status' => ProfileUpdateRequest::STATUS_PENDING,
        ]);

        AuditLog::logMutation('created', Auth::user(), $updateRequest);

        return redirect()
            ->route('phn.update-requests.show', $updateRequest)
            ->with('success', 'Resident correction request forwarded to the assigned Barangay Secretary.');
    }

    public function createHousehold(Request $request): View
    {
        $selectedHousehold = null;

        if ($request->filled('household_id')) {
            $selectedHousehold = $this->phnHouseholdsQuery()
                ->with(['purok', 'headResident', 'residents'])
                ->find($request->integer('household_id'));
        }

        return view('bhw.update-requests.create-household', [
            'routePrefix' => 'phn',
            'pageTitle' => 'PHN Household Correction Request - HealthLink',
            'pageHeader' => 'Household Correction Request',
            'pageSubheader' => 'Send household registry updates back to the assigned Barangay Secretary for approval.',
            'selectedHousehold' => $selectedHousehold,
            'householdOptions' => $this->phnHouseholdsQuery()
                ->with(['purok', 'headResident', 'residents'])
                ->orderBy('household_no')
                ->get(),
            'puroks' => $this->phnPuroksQuery()->active()->orderBy('barangay_id')->orderBy('purok_number')->get(),
            'garbageDisposalMethods' => Household::GARBAGE_DISPOSAL_METHODS,
            'housingMaterialTypes' => Household::HOUSING_MATERIAL_TYPES,
        ]);
    }

    public function storeHousehold(StoreHouseholdUpdateRequest $request): RedirectResponse
    {
        $household = $this->phnHouseholdsQuery()
            ->with('purok', 'headResident')
            ->findOrFail($request->integer('subject_id'));

        $updateRequest = ProfileUpdateRequest::query()->create([
            'submitted_by_user_id' => Auth::id(),
            'barangay_id' => $household->purok->barangay_id,
            'subject_type' => ProfileUpdateRequest::SUBJECT_HOUSEHOLD,
            'subject_id' => $household->id,
            'current_snapshot' => $this->householdSnapshot($household),
            'proposed_changes' => Arr::except($request->validated(), ['subject_id', 'request_reason']),
            'request_reason' => $request->string('request_reason')->toString(),
            'request_status' => ProfileUpdateRequest::STATUS_PENDING,
        ]);

        AuditLog::logMutation('created', Auth::user(), $updateRequest);

        return redirect()
            ->route('phn.update-requests.show', $updateRequest)
            ->with('success', 'Household correction request forwarded to the assigned Barangay Secretary.');
    }

    public function show(ProfileUpdateRequest $profileUpdateRequest): View
    {
        $this->ensureProfileUpdateRequestBelongsToPhn($profileUpdateRequest);

        $profileUpdateRequest->load(['resident.household.purok', 'household.purok', 'reviewedBy']);

        return view('bhw.update-requests.show', [
            'routePrefix' => 'phn',
            'pageTitle' => 'PHN Correction Request Detail - HealthLink',
            'pageHeader' => 'Correction Request Detail',
            'pageSubheader' => 'Review the correction payload sent back to the Secretary queue from the PHN workspace.',
            'updateRequest' => $profileUpdateRequest,
        ]);
    }

    private function residentSnapshot(Resident $resident): array
    {
        return Arr::only($resident->load('household.purok')->toArray(), [
            'official_resident_code',
            'household_id',
            'philsys_card_no',
            'last_name',
            'first_name',
            'middle_name',
            'suffix',
            'birth_date',
            'birth_place',
            'sex',
            'civil_status',
            'citizenship',
            'religion',
            'contact_number',
            'email_address',
            'relationship_to_head',
            'resident_status',
            'moved_in_at',
            'moved_out_at',
            'date_of_death',
            'status_notes',
            'is_active',
        ]);
    }

    private function householdSnapshot(Household $household): array
    {
        return Arr::only($household->load(['purok', 'headResident'])->toArray(), [
            'official_household_code',
            'purok_id',
            'household_no',
            'household_address',
            'drinking_water_source',
            'has_sanitary_toilet',
            'sanitary_toilet_type',
            'garbage_disposal_method',
            'has_backyard_garden',
            'housing_material_type',
            'head_resident_id',
            'is_social_aid_beneficiary',
            'is_active',
        ]);
    }
}
