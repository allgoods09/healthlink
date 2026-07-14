<?php

namespace App\Http\Controllers\Bhw;

use App\Http\Controllers\Bhw\Concerns\InteractsWithBhwScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bhw\StoreHouseholdUpdateRequest;
use App\Http\Requests\Bhw\StoreResidentUpdateRequest;
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
    use InteractsWithBhwScope;

    public function index(Request $request): View
    {
        $query = $this->bhwOwnProfileUpdateRequestsQuery()
            ->with(['resident.household.purok', 'household.purok', 'reviewedBy'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('request_status', $request->string('status')->toString());
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->string('subject_type')->toString());
        }

        return view('bhw.update-requests.index', [
            'updateRequests' => $query->paginate(12)->withQueryString(),
        ]);
    }

    public function createResident(Request $request): View
    {
        $selectedResident = null;

        if ($request->filled('resident_id')) {
            $selectedResident = $this->bhwResidentsQuery()
                ->with('household.purok')
                ->find($request->integer('resident_id'));
        }

        return view('bhw.update-requests.create-resident', [
            'selectedResident' => $selectedResident,
            'residentOptions' => $this->bhwResidentsQuery()
                ->with('household.purok')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'householdOptions' => $this->bhwHouseholdsQuery()
                ->with(['purok', 'headResident'])
                ->orderBy('household_no')
                ->get(),
        ]);
    }

    public function storeResident(StoreResidentUpdateRequest $request): RedirectResponse
    {
        $resident = $this->bhwResidentsQuery()->findOrFail($request->integer('subject_id'));

        $updateRequest = ProfileUpdateRequest::query()->create([
            'submitted_by_user_id' => Auth::id(),
            'barangay_id' => $this->assignedBarangayId(),
            'subject_type' => ProfileUpdateRequest::SUBJECT_RESIDENT,
            'subject_id' => $resident->id,
            'current_snapshot' => $this->residentSnapshot($resident),
            'proposed_changes' => Arr::except($request->validated(), ['subject_id', 'request_reason']),
            'request_reason' => $request->string('request_reason')->toString(),
            'request_status' => ProfileUpdateRequest::STATUS_PENDING,
        ]);

        AuditLog::logMutation('created', Auth::user(), $updateRequest);

        return redirect()
            ->route('bhw.update-requests.show', $updateRequest)
            ->with('success', 'Resident correction request submitted to the secretary.');
    }

    public function createHousehold(Request $request): View
    {
        $selectedHousehold = null;

        if ($request->filled('household_id')) {
            $selectedHousehold = $this->bhwHouseholdsQuery()
                ->with(['purok', 'headResident', 'residents'])
                ->find($request->integer('household_id'));
        }

        return view('bhw.update-requests.create-household', [
            'selectedHousehold' => $selectedHousehold,
            'householdOptions' => $this->bhwHouseholdsQuery()
                ->with(['purok', 'headResident', 'residents'])
                ->orderBy('household_no')
                ->get(),
            'puroks' => $this->bhwPuroksQuery()->active()->orderBy('purok_number')->get(),
            'garbageDisposalMethods' => Household::GARBAGE_DISPOSAL_METHODS,
            'housingMaterialTypes' => Household::HOUSING_MATERIAL_TYPES,
        ]);
    }

    public function storeHousehold(StoreHouseholdUpdateRequest $request): RedirectResponse
    {
        $household = $this->bhwHouseholdsQuery()->findOrFail($request->integer('subject_id'));

        $updateRequest = ProfileUpdateRequest::query()->create([
            'submitted_by_user_id' => Auth::id(),
            'barangay_id' => $this->assignedBarangayId(),
            'subject_type' => ProfileUpdateRequest::SUBJECT_HOUSEHOLD,
            'subject_id' => $household->id,
            'current_snapshot' => $this->householdSnapshot($household),
            'proposed_changes' => Arr::except($request->validated(), ['subject_id', 'request_reason']),
            'request_reason' => $request->string('request_reason')->toString(),
            'request_status' => ProfileUpdateRequest::STATUS_PENDING,
        ]);

        AuditLog::logMutation('created', Auth::user(), $updateRequest);

        return redirect()
            ->route('bhw.update-requests.show', $updateRequest)
            ->with('success', 'Household correction request submitted to the secretary.');
    }

    public function show(ProfileUpdateRequest $profileUpdateRequest): View
    {
        $this->ensureProfileUpdateRequestBelongsToBhw($profileUpdateRequest);

        $profileUpdateRequest->load(['resident.household.purok', 'household.purok', 'reviewedBy']);

        return view('bhw.update-requests.show', [
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
