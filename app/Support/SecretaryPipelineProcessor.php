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

class SecretaryPipelineProcessor
{
    use NormalizesResidentLifecycle;

    public function approveHouseholdDraft(HouseholdDraft $householdDraft, array $payload, User $secretary): Household
    {
        return DB::transaction(function () use ($householdDraft, $payload, $secretary): Household {
            $householdDraft->loadMissing('residentDrafts');

            $oldDraftValues = $householdDraft->toArray();

            $household = Household::create([
                'purok_id' => $payload['purok_id'],
                'household_no' => $payload['household_no'],
                'household_address' => $payload['household_address'],
                'drinking_water_source' => $payload['drinking_water_source'] ?? null,
                'has_sanitary_toilet' => $payload['has_sanitary_toilet'] ?? null,
                'sanitary_toilet_type' => $payload['sanitary_toilet_type'] ?? null,
                'garbage_disposal_method' => $payload['garbage_disposal_method'] ?? null,
                'has_backyard_garden' => $payload['has_backyard_garden'] ?? null,
                'housing_material_type' => $payload['housing_material_type'] ?? null,
                'is_social_aid_beneficiary' => $payload['is_social_aid_beneficiary'] ?? false,
                'is_active' => true,
            ]);

            AuditLog::logMutation('created', $secretary, $household);

            $createdResidents = [];

            foreach ($payload['residents'] as $residentPayload) {
                $resident = Resident::create([
                    'household_id' => $household->id,
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

                ResidentSocioEconomicProfile::query()->updateOrCreate(
                    ['resident_id' => $resident->id],
                    $this->defaultSocioEconomicProfile()
                );

                AuditLog::logMutation('created', $secretary, $resident);

                $createdResidents[(int) $residentPayload['draft_id']] = $resident;
            }

            $headDraftId = $payload['head_draft_id'] ?? null;

            if ($headDraftId && isset($createdResidents[$headDraftId])) {
                $headResident = $createdResidents[$headDraftId];
                $headResidentOldValues = $headResident->toArray();

                $headResident->update([
                    'relationship_to_head' => 'Head of Household',
                ]);

                $household->update([
                    'head_resident_id' => $headResident->id,
                ]);

                AuditLog::logMutation('updated', $secretary, $headResident, $headResidentOldValues, $headResident->fresh()->toArray());
                AuditLog::logMutation('updated', $secretary, $household, ['head_resident_id' => null], $household->fresh()->toArray());
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

    public function applyProfileUpdateRequest(ProfileUpdateRequest $profileUpdateRequest, array $payload, User $secretary): Model
    {
        return DB::transaction(function () use ($profileUpdateRequest, $payload, $secretary): Model {
            $oldRequestValues = $profileUpdateRequest->toArray();

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
        });
    }

    private function applyResidentUpdateRequest(ProfileUpdateRequest $profileUpdateRequest, array $payload, User $secretary): Resident
    {
        $resident = Resident::query()->findOrFail($profileUpdateRequest->subject_id);
        $resident->loadMissing('household');

        $oldResidentValues = $resident->load('household.purok', 'socioEconomicProfile')->toArray();
        $oldHousehold = $resident->household;
        $targetHousehold = Household::query()->findOrFail($payload['household_id']);
        $data = $this->normalizeResidentLifecycle(Arr::except($payload, ['review_notes']));

        $wasOldHead = (int) $oldHousehold?->head_resident_id === (int) $resident->id;
        $relationship = $data['relationship_to_head'];

        $resident->update($data);

        if ($wasOldHead && (int) $oldHousehold->id !== (int) $targetHousehold->id) {
            $oldHouseholdOldValues = $oldHousehold->toArray();

            $oldHousehold->update(['head_resident_id' => null]);

            AuditLog::logMutation('updated', $secretary, $oldHousehold, $oldHouseholdOldValues, $oldHousehold->fresh()->toArray());
        }

        if ($relationship === 'Head of Household') {
            $targetHouseholdOldValues = $targetHousehold->toArray();

            $targetHousehold->update(['head_resident_id' => $resident->id]);

            AuditLog::logMutation('updated', $secretary, $targetHousehold, $targetHouseholdOldValues, $targetHousehold->fresh()->toArray());
        } elseif ((int) $targetHousehold->head_resident_id === (int) $resident->id) {
            $targetHouseholdOldValues = $targetHousehold->toArray();

            $targetHousehold->update(['head_resident_id' => null]);

            AuditLog::logMutation('updated', $secretary, $targetHousehold, $targetHouseholdOldValues, $targetHousehold->fresh()->toArray());
        }

        AuditLog::logMutation('updated', $secretary, $resident, $oldResidentValues, $resident->fresh()->load('household.purok', 'socioEconomicProfile')->toArray());

        return $resident->fresh(['household.purok', 'socioEconomicProfile']);
    }

    private function applyHouseholdUpdateRequest(ProfileUpdateRequest $profileUpdateRequest, array $payload, User $secretary): Household
    {
        $household = Household::query()->findOrFail($profileUpdateRequest->subject_id);
        $oldHouseholdValues = $household->load('purok', 'headResident')->toArray();

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
