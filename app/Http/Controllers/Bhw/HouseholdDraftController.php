<?php

namespace App\Http\Controllers\Bhw;

use App\Http\Controllers\Bhw\Concerns\InteractsWithBhwScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bhw\StoreHouseholdDraftRequest;
use App\Http\Requests\Bhw\UpdateHouseholdDraftRequest;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\HouseholdDraft;
use App\Models\ResidentDraft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HouseholdDraftController extends Controller
{
    use InteractsWithBhwScope;

    public function index(Request $request): View
    {
        $query = $this->bhwOwnHouseholdDraftsQuery()
            ->with(['purok', 'reviewedBy'])
            ->withCount('residentDrafts')
            ->latest();

        if ($request->filled('status')) {
            $query->where('draft_status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($draftQuery) use ($search): void {
                $draftQuery->where('draft_reference_code', 'like', "%{$search}%")
                    ->orWhere('household_address', 'like', "%{$search}%");
            });
        }

        return view('bhw.drafts.index', [
            'drafts' => $query->paginate(12)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('bhw.drafts.create', [
            'puroks' => $this->bhwPuroksQuery()->active()->orderBy('purok_number')->get(),
            'defaultPurokId' => $this->assignedPurokId(),
            'garbageDisposalMethods' => Household::GARBAGE_DISPOSAL_METHODS,
            'housingMaterialTypes' => Household::HOUSING_MATERIAL_TYPES,
        ]);
    }

    public function store(StoreHouseholdDraftRequest $request): RedirectResponse
    {
        $draft = DB::transaction(function () use ($request): HouseholdDraft {
            $draft = HouseholdDraft::query()->create([
                'submitted_by_user_id' => Auth::id(),
                'barangay_id' => $this->assignedBarangayId(),
                'purok_id' => $request->integer('purok_id'),
                'household_address' => $request->string('household_address')->toString(),
                'drinking_water_source' => $request->input('drinking_water_source'),
                'has_sanitary_toilet' => $request->boolean('has_sanitary_toilet'),
                'sanitary_toilet_type' => $request->input('sanitary_toilet_type'),
                'garbage_disposal_method' => $request->input('garbage_disposal_method'),
                'has_backyard_garden' => $request->boolean('has_backyard_garden'),
                'housing_material_type' => $request->input('housing_material_type'),
                'is_social_aid_beneficiary' => $request->boolean('is_social_aid_beneficiary'),
                'draft_status' => HouseholdDraft::STATUS_PENDING,
            ]);

            foreach ($request->validated('residents') as $residentPayload) {
                $residentDraft = $draft->residentDrafts()->create([
                    'philsys_card_no' => $residentPayload['philsys_card_no'] ?? null,
                    'last_name' => $residentPayload['last_name'],
                    'first_name' => $residentPayload['first_name'],
                    'middle_name' => $residentPayload['middle_name'] ?? null,
                    'suffix' => $residentPayload['suffix'] ?? null,
                    'birth_date' => $residentPayload['birth_date'],
                    'birth_place' => $residentPayload['birth_place'],
                    'sex' => $residentPayload['sex'],
                    'civil_status' => $residentPayload['civil_status'],
                    'citizenship' => $residentPayload['citizenship'],
                    'religion' => $residentPayload['religion'] ?? null,
                    'contact_number' => $residentPayload['contact_number'] ?? null,
                    'email_address' => $residentPayload['email_address'] ?? null,
                    'relationship_to_head' => $residentPayload['relationship_to_head'],
                    'is_household_head_candidate' => ! empty($residentPayload['is_household_head_candidate']),
                    'draft_notes' => $residentPayload['draft_notes'] ?? null,
                ]);

                AuditLog::logMutation('created', Auth::user(), $residentDraft);
            }

            AuditLog::logMutation('created', Auth::user(), $draft);

            return $draft;
        });

        return redirect()
            ->route('bhw.drafts.show', $draft)
            ->with('success', 'Field draft package submitted for secretary verification.');
    }

    public function show(HouseholdDraft $householdDraft): View
    {
        $this->ensureDraftBelongsToBhw($householdDraft);

        $householdDraft->load(['purok', 'residentDrafts', 'reviewedBy', 'approvedHousehold']);

        return view('bhw.drafts.show', [
            'draft' => $householdDraft,
        ]);
    }

    public function edit(HouseholdDraft $householdDraft): View
    {
        $this->ensureDraftBelongsToBhw($householdDraft);

        if ($householdDraft->draft_status !== HouseholdDraft::STATUS_PENDING) {
            abort(403, 'Only pending field draft packages can still be edited.');
        }

        $householdDraft->load('residentDrafts');

        return view('bhw.drafts.edit', [
            'draft' => $householdDraft,
            'puroks' => $this->bhwPuroksQuery()->active()->orderBy('purok_number')->get(),
            'garbageDisposalMethods' => Household::GARBAGE_DISPOSAL_METHODS,
            'housingMaterialTypes' => Household::HOUSING_MATERIAL_TYPES,
        ]);
    }

    public function update(UpdateHouseholdDraftRequest $request, HouseholdDraft $householdDraft): RedirectResponse
    {
        $this->ensureDraftBelongsToBhw($householdDraft);

        if ($householdDraft->draft_status !== HouseholdDraft::STATUS_PENDING) {
            abort(403, 'Only pending field draft packages can still be edited.');
        }

        DB::transaction(function () use ($request, $householdDraft): void {
            $oldValues = $householdDraft->load('residentDrafts')->toArray();

            $householdDraft->update([
                'purok_id' => $request->integer('purok_id'),
                'household_address' => $request->string('household_address')->toString(),
                'drinking_water_source' => $request->input('drinking_water_source'),
                'has_sanitary_toilet' => $request->boolean('has_sanitary_toilet'),
                'sanitary_toilet_type' => $request->input('sanitary_toilet_type'),
                'garbage_disposal_method' => $request->input('garbage_disposal_method'),
                'has_backyard_garden' => $request->boolean('has_backyard_garden'),
                'housing_material_type' => $request->input('housing_material_type'),
                'is_social_aid_beneficiary' => $request->boolean('is_social_aid_beneficiary'),
            ]);

            $householdDraft->residentDrafts()->delete();

            foreach ($request->validated('residents') as $residentPayload) {
                $householdDraft->residentDrafts()->create([
                    'philsys_card_no' => $residentPayload['philsys_card_no'] ?? null,
                    'last_name' => $residentPayload['last_name'],
                    'first_name' => $residentPayload['first_name'],
                    'middle_name' => $residentPayload['middle_name'] ?? null,
                    'suffix' => $residentPayload['suffix'] ?? null,
                    'birth_date' => $residentPayload['birth_date'],
                    'birth_place' => $residentPayload['birth_place'],
                    'sex' => $residentPayload['sex'],
                    'civil_status' => $residentPayload['civil_status'],
                    'citizenship' => $residentPayload['citizenship'],
                    'religion' => $residentPayload['religion'] ?? null,
                    'contact_number' => $residentPayload['contact_number'] ?? null,
                    'email_address' => $residentPayload['email_address'] ?? null,
                    'relationship_to_head' => $residentPayload['relationship_to_head'],
                    'is_household_head_candidate' => ! empty($residentPayload['is_household_head_candidate']),
                    'draft_notes' => $residentPayload['draft_notes'] ?? null,
                ]);
            }

            AuditLog::logMutation('updated', Auth::user(), $householdDraft, $oldValues, $householdDraft->fresh()->load('residentDrafts')->toArray());
        });

        return redirect()
            ->route('bhw.drafts.show', $householdDraft)
            ->with('success', 'Field draft package updated successfully.');
    }
}
