<?php

namespace App\Support;

use App\Http\Controllers\Concerns\NormalizesResidentLifecycle;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\HouseholdDraft;
use App\Models\ProfileUpdateRequest;
use App\Models\Resident;
use App\Models\ResidentSocioEconomicProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SecretaryPipelineProcessor
{
    use NormalizesResidentLifecycle;

    public function approveHouseholdDraft(HouseholdDraft $householdDraft, array $payload, User $secretary): Household
    {
        return DB::transaction(function () use ($householdDraft, $payload, $secretary): Household {
            $householdDraft = HouseholdDraft::query()->lockForUpdate()->findOrFail($householdDraft->id);
            if ($householdDraft->draft_status !== HouseholdDraft::STATUS_PENDING) {
                throw ValidationException::withMessages(['residents' => 'This field draft has already been reviewed.']);
            }
            $householdDraft->loadMissing('residentDrafts');

            $oldDraftValues = $householdDraft->toArray();

            $household = $householdDraft->target_household_id
                ? Household::query()->whereHas('purok', fn ($query) => $query->where('barangay_id', $householdDraft->barangay_id))->findOrFail($householdDraft->target_household_id)
                : Household::create([
                    'purok_id' => $payload['purok_id'],
                    'household_no' => $payload['household_no'],
                    'household_address' => $payload['household_address'],
                    'mobile_uuid' => $householdDraft->mobile_uuid,
                    'drinking_water_source' => $payload['drinking_water_source'] ?? null,
                    'has_sanitary_toilet' => $payload['has_sanitary_toilet'] ?? null,
                    'sanitary_toilet_type' => $payload['sanitary_toilet_type'] ?? null,
                    'garbage_disposal_method' => $payload['garbage_disposal_method'] ?? null,
                    'has_backyard_garden' => $payload['has_backyard_garden'] ?? null,
                    'housing_material_type' => $payload['housing_material_type'] ?? null,
                    'is_social_aid_beneficiary' => $payload['is_social_aid_beneficiary'] ?? false,
                    'is_active' => true,
                ]);

            if (! $householdDraft->target_household_id) {
                AuditLog::logMutation('created', $secretary, $household);
            }

            $createdResidents = [];

            foreach ($payload['residents'] ?? [] as $residentPayload) {
                $sourceDraft = $householdDraft->residentDrafts->firstWhere('id', (int) $residentPayload['draft_id']);
                $resident = Resident::create([
                    'household_id' => $household->id,
                    'mobile_uuid' => $sourceDraft?->mobile_uuid,
                    'philsys_card_no' => $residentPayload['philsys_card_no'] ?? null,
                    'last_name' => $residentPayload['last_name'],
                    'first_name' => $residentPayload['first_name'],
                    'middle_name' => $residentPayload['middle_name'] ?? null,
                    'suffix' => $residentPayload['suffix'] ?? null,
                    'birth_date' => $residentPayload['birth_date'],
                    'birth_place' => $residentPayload['birth_place'],
                    'sex' => $residentPayload['sex'],
                    'civil_status' => $residentPayload['civil_status'],
                    'citizenship' => $residentPayload['citizenship'] ?? 'Filipino',
                    'religion' => $residentPayload['religion'] ?? null,
                    'contact_number' => $residentPayload['contact_number'] ?? null,
                    'email_address' => $residentPayload['email_address'] ?? null,
                    'relationship_to_head' => $residentPayload['relationship_to_head'],
                    'resident_status' => Resident::STATUS_ACTIVE,
                    'is_active' => true,
                ]);

                ResidentProfileData::persist($resident, $residentPayload, true);

                AuditLog::logMutation('created', $secretary, $resident);

                $createdResidents[(int) $residentPayload['draft_id']] = $resident;
            }

            $headDraftId = $payload['head_draft_id'] ?? null;

            if (! $householdDraft->target_household_id && $headDraftId && isset($createdResidents[$headDraftId])) {
                $relationships = $household->residents()->whereKeyNot($createdResidents[$headDraftId]->id)
                    ->pluck('relationship_to_head', 'id')->all();
                app(HouseholdHeadManager::class)->designate($household, $createdResidents[$headDraftId], $relationships);
            }

            foreach ($householdDraft->residentDrafts as $residentDraft) {
                $approvedResident = $createdResidents[$residentDraft->id] ?? null;

                if (! $approvedResident) {
                    continue;
                }

                $residentDraft->forceFill([
                    'approved_resident_id' => $approvedResident->id,
                ])->save();
            }

            $householdDraft->forceFill([
                'draft_status' => HouseholdDraft::STATUS_APPROVED,
                'reviewed_by_user_id' => $secretary->id,
                'reviewed_at' => now(),
                'verification_notes' => $payload['verification_notes'] ?? null,
                'approved_household_id' => $household->id,
            ])->save();

            AuditLog::logMutation('updated', $secretary, $householdDraft, $oldDraftValues, $householdDraft->fresh()->toArray());

            return $household->fresh(['purok', 'headResident', 'residents']);
        });
    }

    public function applyProfileUpdateRequest(ProfileUpdateRequest $profileUpdateRequest, array $payload, User $secretary): Model|View
    {
        $apply = function (array $payload) use ($profileUpdateRequest, $secretary): Model {
            $profileUpdateRequest = ProfileUpdateRequest::query()->lockForUpdate()->findOrFail($profileUpdateRequest->id);
            if ($profileUpdateRequest->request_status !== ProfileUpdateRequest::STATUS_PENDING) {
                throw ValidationException::withMessages(['review_notes' => 'This correction request has already been reviewed.']);
            }
            $oldRequestValues = $profileUpdateRequest->toArray();

            if ($profileUpdateRequest->subject_type === ProfileUpdateRequest::SUBJECT_HOUSEHOLD && $profileUpdateRequest->mobile_submission_key) {
                $payload = Arr::only($payload, [...array_intersect(array_keys($profileUpdateRequest->proposed_changes ?? []), MobileHouseholdRequestData::EDITABLE), 'review_notes']);
            }

            $subject = match ($profileUpdateRequest->subject_type) {
                ProfileUpdateRequest::SUBJECT_RESIDENT => $this->applyResidentUpdateRequest($profileUpdateRequest, $payload, $secretary),
                ProfileUpdateRequest::SUBJECT_HOUSEHOLD => $this->applyHouseholdUpdateRequest($profileUpdateRequest, $payload, $secretary),
                default => throw new \RuntimeException('Unsupported update request subject.'),
            };

            $profileUpdateRequest->forceFill([
                'request_status' => ProfileUpdateRequest::STATUS_APPROVED,
                'reviewed_by_user_id' => $secretary->id,
                'reviewed_at' => now(),
                'review_notes' => $payload['review_notes'] ?? null,
                'applied_at' => now(),
                'proposed_changes' => Arr::except($payload, ['review_notes']),
            ])->save();

            AuditLog::logMutation('updated', $secretary, $profileUpdateRequest, $oldRequestValues, $profileUpdateRequest->fresh()->toArray());

            return $subject;
        };
        $workflow = app(HouseholdHeadReview::class);

        if ($profileUpdateRequest->subject_type === ProfileUpdateRequest::SUBJECT_HOUSEHOLD && $profileUpdateRequest->mobile_submission_key) {
            // Mobile Household proposals cannot change heads or unrelated form fields.
            return DB::transaction(fn () => $apply($payload));
        }

        return match ($profileUpdateRequest->subject_type) {
            ProfileUpdateRequest::SUBJECT_RESIDENT => $workflow->resident(request(), $payload,
                Resident::findOrFail($profileUpdateRequest->subject_id), fn ($data) => $apply($data), true),
            ProfileUpdateRequest::SUBJECT_HOUSEHOLD => $workflow->household(request(), $payload,
                Household::findOrFail($profileUpdateRequest->subject_id), fn ($data) => $apply($data)),
            default => throw new \RuntimeException('Unsupported update request subject.'),
        };
    }

    private function applyResidentUpdateRequest(ProfileUpdateRequest $profileUpdateRequest, array $payload, User $secretary): Resident
    {
        $resident = Resident::query()->lockForUpdate()->findOrFail($profileUpdateRequest->subject_id);
        $resident->setRelation('socioEconomicProfile', $resident->socioEconomicProfile()->lockForUpdate()->first());
        $resident->loadMissing('household');

        if ($profileUpdateRequest->mobile_submission_key) {
            MobileResidentRequestData::assertUnchanged($resident, $profileUpdateRequest->current_snapshot ?? []);
        }

        $oldResidentValues = $resident->load('household.purok', 'socioEconomicProfile')->toArray();
        $data = $profileUpdateRequest->mobile_submission_key
            ? Arr::only($payload, [...MobileResidentRequestData::EDITABLE, 'philsys_card_no', 'is_active'])
            : $this->normalizeResidentLifecycle(Arr::except($payload, ['review_notes', 'set_as_household_head']));

        $resident->update(Arr::except($data, ResidentProfileData::FIELDS));
        ResidentProfileData::persist($resident, $payload);

        AuditLog::logMutation('updated', $secretary, $resident, $oldResidentValues, $resident->fresh()->load('household.purok', 'socioEconomicProfile')->toArray());

        return $resident->fresh(['household.purok', 'socioEconomicProfile']);
    }

    private function applyHouseholdUpdateRequest(ProfileUpdateRequest $profileUpdateRequest, array $payload, User $secretary): Household
    {
        $household = Household::query()->lockForUpdate()->findOrFail($profileUpdateRequest->subject_id);
        $oldHouseholdValues = $household->load('purok', 'headResident')->toArray();

        if ($profileUpdateRequest->mobile_submission_key) {
            $base = $profileUpdateRequest->current_snapshot ?? [];
            if (($base['_household_contract_version'] ?? 0) !== MobileHouseholdRequestData::VERSION) {
                throw \Illuminate\Validation\ValidationException::withMessages(['household' =>
                    'This legacy mobile correction has no downloaded baseline. Preserve it for review and prepare a fresh correction.']);
            }
            $fields = array_keys($profileUpdateRequest->proposed_changes ?? []);
            MobileHouseholdRequestData::assertUnchanged($household, $base, $fields);
            $payload = Arr::only($payload, array_intersect($fields, MobileHouseholdRequestData::EDITABLE));
        }

        $household->update(Arr::except($payload, ['review_notes']));

        AuditLog::logMutation('updated', $secretary, $household, $oldHouseholdValues, $household->fresh()->load('purok', 'headResident')->toArray());

        return $household->fresh(['purok', 'headResident', 'residents']);
    }

    private function defaultSocioEconomicProfile(): array
    {
        return [
            'employment_status' => 'N/A',
            'highest_education_level' => 'None',
            'education_status' => 'N/A',
            'is_pwd' => false,
            'is_ofw' => false,
            'is_solo_parent' => false,
            'is_osy' => false,
            'is_osc' => false,
            'is_ip' => false,
        ];
    }
}
