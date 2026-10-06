<?php

namespace App\Support;

use App\Models\FieldVisit;
use App\Models\Household;
use App\Models\HouseholdDraft;
use App\Models\PhilpenRiskAssessment;
use App\Models\ProfileUpdateRequest;
use App\Models\Resident;
use App\Models\ResidentDraft;
use App\Models\User;

class MobileBootstrapPayload
{
    /**
     * Build a full bootstrap payload for the authenticated BHW.
     */
    public function build(User $user): array
    {
        $assignedPurok = MobileBarangayScope::requirePurok($user);
        $barangayId = $assignedPurok->barangay_id;

        $user->load(['assignedBarangay', 'assignedPurok.barangay']);

        $households = Household::query()
            ->whereHas('purok', fn ($purokQuery) => $purokQuery->where('barangay_id', $barangayId))
            ->with([
                'purok.barangay', 'headResident',
            ])
            ->withCount('residents')
            ->withCount('currentMembers')
            ->orderBy('household_no')
            ->get();

        $residents = Resident::query()
            ->currentPopulation()
            ->whereHas('household', fn ($query) => $query->where('purok_id', $assignedPurok->id))
            ->with([
                'household:id,mobile_uuid', 'socioEconomicProfile',
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('middle_name')
            ->orderBy('suffix')
            ->orderBy('id')
            ->get();

        $fieldVisits = FieldVisit::query()
            ->whereHas('household.purok', fn ($purokQuery) => $purokQuery->where('barangay_id', $barangayId))
            ->with([
                'household:id,mobile_uuid',
                'recordedBy:id,name',
            ])
            ->latest('visited_at')
            ->get();

        $riskAssessments = PhilpenRiskAssessment::query()
            ->where('barangay_id', $barangayId)
            ->with([
                'recordedBy:id,name',
            ])
            ->orderByDesc('assessment_date')
            ->orderByDesc('id')
            ->get();

        $drafts = HouseholdDraft::query()
            ->where('submitted_by_user_id', $user->id)
            ->where('barangay_id', $barangayId)
            ->whereIn('draft_status', [HouseholdDraft::STATUS_PENDING, HouseholdDraft::STATUS_REJECTED])
            ->with(['purok', 'targetHousehold'])
            ->get();
        $residentDrafts = ResidentDraft::query()
            ->whereHas('householdDraft', fn ($query) => $query
                ->where('submitted_by_user_id', $user->id)
                ->where('barangay_id', $barangayId)
                ->whereIn('draft_status', [HouseholdDraft::STATUS_PENDING, HouseholdDraft::STATUS_REJECTED]))
            ->whereNotNull('mobile_uuid')
            ->with('householdDraft.targetHousehold')
            ->get();
        $corrections = ProfileUpdateRequest::query()
            ->where('submitted_by_user_id', $user->id)
            ->where('barangay_id', $barangayId)
            ->whereNotNull('mobile_submission_key')
            ->latest('id')->get()
            ->unique(fn (ProfileUpdateRequest $item) => $item->subject_type.':'.$item->subject_id)
            ->keyBy(fn (ProfileUpdateRequest $item) => $item->subject_type.':'.$item->subject_id);

        return [
            'server_time' => now()->toIso8601String(),
            'resident_contract_version' => 2,
            'resident_relationship_choices' => HouseholdRelationships::choices(),
            'resident_profile_choices' => ResidentProfileData::CHOICES,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'approval_status' => $user->approval_status,
                'assignment_label' => $user->assignment_label,
                'locale' => 'en',
            ],
            'assignment' => [
                'barangay' => $user->assignedBarangay ? [
                    'id' => $user->assignedBarangay->id,
                    'name' => $user->assignedBarangay->name,
                    'municipality' => $user->assignedBarangay->municipality,
                    'province' => $user->assignedBarangay->province,
                ] : null,
                'purok' => $user->assignedPurok ? [
                    'id' => $user->assignedPurok->id,
                    'purok_number' => $user->assignedPurok->purok_number,
                    'purok_name' => $user->assignedPurok->purok_name,
                    'display_name' => $user->assignedPurok->display_name,
                ] : null,
            ],
            'households' => $households
                ->map(function (Household $household) use ($corrections) {
                    $correction = $corrections->get('household:'.$household->id);
                    return [...$this->householdPayload($household),
                        'local_revision' => $this->correctionRevision($correction),
                        'verification_status' => $this->reviewStatus($correction),
                        'verification_notes' => $correction?->review_notes];
                })
                ->concat($drafts->filter(fn (HouseholdDraft $draft) => $draft->mobile_uuid && ! $draft->target_household_id)
                    ->map(fn (HouseholdDraft $draft) => [
                        'id' => null,
                        'mobile_uuid' => $draft->mobile_uuid,
                        'purok_id' => $draft->purok_id,
                        'purok_display_name' => $draft->purok?->display_name,
                        'household_no' => $draft->proposed_household_no,
                        'household_address' => $draft->household_address,
                        'is_social_aid_beneficiary' => $draft->is_social_aid_beneficiary,
                        'is_active' => true,
                        'verification_status' => $draft->draft_status === HouseholdDraft::STATUS_PENDING ? 'submitted' : 'rejected',
                        'local_revision' => $draft->mobile_revision,
                        'verification_notes' => $draft->verification_notes,
                        'updated_at' => $draft->updated_at?->toIso8601String(),
                    ]))
                ->values()
                ->all(),
            'residents' => $residents
                ->map(function ($resident) use ($corrections) {
                    $correction = $corrections->get('resident:'.$resident->id);
                    return [
                    'id' => $resident->id,
                    'mobile_uuid' => $resident->mobile_uuid,
                    'household_id' => $resident->household_id,
                    'household_mobile_uuid' => $resident->household?->mobile_uuid,
                    'philsys_card_no' => $resident->philsys_card_no,
                    'last_name' => $resident->last_name,
                    'first_name' => $resident->first_name,
                    'middle_name' => $resident->middle_name,
                    'suffix' => $resident->suffix,
                    'birth_date' => optional($resident->birth_date)->toDateString(),
                    'birth_place' => $resident->birth_place,
                    'sex' => $resident->sex,
                    'civil_status' => $resident->civil_status,
                    'citizenship' => $resident->citizenship,
                    'religion' => $resident->religion,
                    'contact_number' => $resident->contact_number,
                    'email_address' => $resident->email_address,
                    'relationship_to_head' => $resident->relationship_to_head,
                    ...ResidentProfileData::snapshot($resident),
                    'is_active' => $resident->is_active,
                    'resident_status' => $resident->resident_status,
                    'official_snapshot' => MobileResidentRequestData::snapshot($resident),
                    'deleted_at' => null,
                    'verification_status' => $this->reviewStatus($correction),
                    'local_revision' => $this->correctionRevision($correction),
                    'verification_notes' => $correction?->review_notes,
                    'updated_at' => optional($resident->updated_at)->toIso8601String(),
                    ];
                })
                ->concat($residentDrafts->map(function (ResidentDraft $draft) {
                    $parent = $draft->householdDraft;
                    return [
                        'id' => null,
                        'mobile_uuid' => $draft->mobile_uuid,
                        'household_id' => $parent->target_household_id,
                        'household_mobile_uuid' => $parent->mobile_uuid ?? $parent->targetHousehold?->mobile_uuid,
                        'philsys_card_no' => $draft->philsys_card_no,
                        'last_name' => $draft->last_name,
                        'first_name' => $draft->first_name,
                        'middle_name' => $draft->middle_name,
                        'suffix' => $draft->suffix,
                        'birth_date' => $draft->birth_date?->toDateString(),
                        'birth_place' => $draft->birth_place,
                        'sex' => $draft->sex,
                        'civil_status' => $draft->civil_status,
                        'citizenship' => $draft->citizenship,
                        'religion' => $draft->religion,
                        'contact_number' => $draft->contact_number,
                        'email_address' => $draft->email_address,
                        'relationship_to_head' => $draft->relationship_to_head,
                        ...$draft->only(ResidentProfileData::FIELDS),
                        'is_active' => true,
                        'propose_household_head' => $draft->is_household_head_candidate,
                        'verification_status' => $parent->draft_status === HouseholdDraft::STATUS_PENDING ? 'submitted' : 'rejected',
                        'local_revision' => $draft->mobile_revision,
                        'verification_notes' => $parent->verification_notes,
                        'updated_at' => $draft->updated_at?->toIso8601String(),
                    ];
                }))
                ->values()
                ->all(),
            'field_visits' => $fieldVisits
                ->map(fn ($visit) => [
                    'id' => $visit->id,
                    'mobile_uuid' => $visit->mobile_uuid,
                    'household_id' => $visit->household_id,
                    'household_mobile_uuid' => $visit->household?->mobile_uuid,
                    'recorded_by_user_id' => $visit->recorded_by_user_id,
                    'recorded_by_name' => $visit->recordedBy?->name,
                    'visited_at' => optional($visit->visited_at)->toIso8601String(),
                    'notes' => $visit->notes,
                    'photo_count' => $visit->photo_count,
                    'photos' => collect($visit->photos ?? [])->map(fn (array $photo) => [
                        'path' => $photo['path'] ?? null,
                        'file_name' => $photo['file_name'] ?? null,
                        'mime_type' => $photo['mime_type'] ?? null,
                        'file_size_bytes' => $photo['file_size_bytes'] ?? null,
                        'captured_at' => $photo['captured_at'] ?? null,
                    ])->values()->all(),
                    'updated_at' => optional($visit->updated_at)->toIso8601String(),
                ])
                ->values()
                ->all(),
            'risk_assessments' => $riskAssessments
                ->map(fn (PhilpenRiskAssessment $assessment) => [
                    'id' => $assessment->id,
                    'mobile_uuid' => $assessment->mobile_uuid,
                    'resident_id' => $assessment->resident_id,
                    'recorded_by_user_id' => $assessment->recorded_by_user_id,
                    'recorded_by_name' => $assessment->recordedBy?->name,
                    'assessment_date' => optional($assessment->assessment_date)->toDateString(),
                    'age_years' => $assessment->age_years,
                    'religion' => $assessment->religion,
                    'contact_number' => $assessment->contact_number,
                    'philhealth_number' => $assessment->philhealth_number,
                    'civil_status' => $assessment->civil_status,
                    'ethnicity' => $assessment->ethnicity,
                    'pwd_id_number' => $assessment->pwd_id_number,
                    'weight_kg' => $assessment->weight_kg,
                    'height_cm' => $assessment->height_cm,
                    'body_mass_index' => $assessment->body_mass_index,
                    'waist_circumference_cm' => $assessment->waist_circumference_cm,
                    'systolic_bp' => $assessment->systolic_bp,
                    'diastolic_bp' => $assessment->diastolic_bp,
                    'employment_status' => $assessment->employment_status,
                    'ip_classification' => $assessment->ip_classification,
                    'requires_immediate_referral' => $assessment->requires_immediate_referral,
                    'identity_snapshot' => $assessment->identity_snapshot,
                    'red_flags' => $assessment->red_flags,
                    'past_medical_history' => $assessment->past_medical_history,
                    'family_history' => $assessment->family_history,
                    'tobacco_use' => $assessment->tobacco_use,
                    'alcohol_consumption_status' => $assessment->alcohol_consumption_status,
                    'alcohol_binge_flag' => $assessment->alcohol_binge_flag,
                    'physical_activity_met' => $assessment->physical_activity_met,
                    'high_risk_diet_weekly' => $assessment->high_risk_diet_weekly,
                    'blood_sugar_notes' => $assessment->blood_sugar_notes,
                    'fbs_result' => $assessment->fbs_result,
                    'rbs_result' => $assessment->rbs_result,
                    'dm_symptoms' => $assessment->dm_symptoms,
                    'lipid_profile_date' => optional($assessment->lipid_profile_date)->toDateString(),
                    'total_cholesterol' => $assessment->total_cholesterol,
                    'hdl' => $assessment->hdl,
                    'ldl' => $assessment->ldl,
                    'vldl' => $assessment->vldl,
                    'triglycerides' => $assessment->triglycerides,
                    'urinalysis_protein' => $assessment->urinalysis_protein,
                    'urinalysis_ketones' => $assessment->urinalysis_ketones,
                    'urinalysis_date' => optional($assessment->urinalysis_date)->toDateString(),
                    'chronic_respiratory_symptoms' => $assessment->chronic_respiratory_symptoms,
                    'lifestyle_modification' => $assessment->lifestyle_modification,
                    'anti_hypertensive_medications' => $assessment->anti_hypertensive_medications,
                    'oral_hypoglycemic_medications' => $assessment->oral_hypoglycemic_medications,
                    'follow_up_date' => optional($assessment->follow_up_date)->toDateString(),
                    'remarks' => $assessment->remarks,
                    'updated_at' => optional($assessment->updated_at)->toIso8601String(),
                ])
                ->values()
                ->all(),
            'sync' => [
                'mode' => 'full-bootstrap',
                'requires_initial_download' => true,
                'supports_manual_upload' => true,
                'supports_auto_upload_when_online' => false,
                'supported_locales' => ['en', 'ceb'],
            ],
        ];
    }

    private function reviewStatus(?ProfileUpdateRequest $request): string
    {
        return match ($request?->request_status) {
            ProfileUpdateRequest::STATUS_PENDING => 'submitted',
            ProfileUpdateRequest::STATUS_REJECTED => 'rejected',
            default => 'approved',
        };
    }

    private function correctionRevision(?ProfileUpdateRequest $request): int
    {
        if (! $request?->mobile_submission_key) return 0;
        return (int) str($request->mobile_submission_key)->afterLast(':')->toString();
    }

    /**
     * Build a mobile-friendly household payload.
     */
    private function householdPayload(Household $household): array
    {
        return [
            'id' => $household->id,
            'mobile_uuid' => $household->mobile_uuid,
            'purok_id' => $household->purok_id,
            'purok_display_name' => $household->purok?->display_name,
            'household_no' => $household->household_no,
            'household_address' => $household->household_address,
            'is_social_aid_beneficiary' => $household->is_social_aid_beneficiary,
            'is_active' => $household->is_active,
            'resident_count' => $household->residents_count ?? $household->residents()->count(),
            'current_head_name' => $household->currentHeadResident()?->formal_name,
            'is_vacant' => ($household->current_members_count ?? $household->currentMemberCount()) === 0,
            'updated_at' => optional($household->updated_at)->toIso8601String(),
        ];
    }
}
