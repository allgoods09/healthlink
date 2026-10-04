<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Household;
use App\Models\HouseholdDraft;
use App\Models\ProfileUpdateRequest;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\ResidentDraft;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class MobileRegistrySubmissionService
{
    private const HOUSEHOLD_FIELDS = ['household_no', 'household_address', 'is_social_aid_beneficiary', 'is_active'];

    private const RESIDENT_FIELDS = [
        'philsys_card_no', 'last_name', 'first_name', 'middle_name', 'suffix', 'birth_date',
        'birth_place', 'sex', 'civil_status', 'citizenship', 'religion', 'contact_number',
        'email_address', 'relationship_to_head', 'is_active',
    ];

    public function __construct(private readonly RoleNotificationService $notifications) {}

    public function submitHousehold(User $user, array $input): array
    {
        $purok = $this->assignedPurok($user);
        $uuid = $input['mobile_uuid'] ?? null;
        $official = $this->officialHousehold($user, $input['id'] ?? null, $uuid);

        if ($official) {
            return $this->submitCorrection($user, $official, 'household', $input,
                Arr::only($input, self::HOUSEHOLD_FIELDS));
        }

        if (! $uuid || isset($input['id'])) {
            throw new \RuntimeException('Household not found in your assigned purok.');
        }

        $attributes = Arr::only($input, self::HOUSEHOLD_FIELDS);
        foreach (self::HOUSEHOLD_FIELDS as $field) {
            if (! array_key_exists($field, $attributes)) throw new \RuntimeException("New households require '{$field}'.");
        }
        if (! $attributes['is_active']) throw new \RuntimeException('New household submissions must be active.');

        $created = false;
        $draft = DB::transaction(function () use ($user, $purok, $uuid, $attributes, $input, &$created): HouseholdDraft {
            $draft = HouseholdDraft::query()->where('mobile_uuid', $uuid)->lockForUpdate()->first();
            if ($draft && ((int) $draft->submitted_by_user_id !== (int) $user->id ||
                (int) $draft->purok_id !== (int) $purok->id || $draft->target_household_id)) {
                throw new \RuntimeException('This household submission belongs to another assignment.');
            }
            if (! $draft) {
                $draft = HouseholdDraft::query()->create([
                    'mobile_uuid' => $uuid,
                    'submitted_by_user_id' => $user->id,
                    'barangay_id' => $purok->barangay_id,
                    'purok_id' => $purok->id,
                    'proposed_household_no' => $attributes['household_no'],
                    'household_address' => $attributes['household_address'],
                    'is_social_aid_beneficiary' => $attributes['is_social_aid_beneficiary'],
                    'mobile_revision' => $input['local_revision'] ?? 0,
                    'draft_status' => HouseholdDraft::STATUS_PENDING,
                ]);
                AuditLog::logMutation('created', $user, $draft);
                $created = true;
            } elseif ($draft->draft_status === HouseholdDraft::STATUS_PENDING &&
                ($input['local_revision'] ?? 0) > $draft->mobile_revision) {
                $old = $draft->toArray();
                $draft->update([
                    'proposed_household_no' => $attributes['household_no'],
                    'household_address' => $attributes['household_address'],
                    'is_social_aid_beneficiary' => $attributes['is_social_aid_beneficiary'],
                    'mobile_revision' => $input['local_revision'],
                ]);
                AuditLog::logMutation('updated', $user, $draft, $old, $draft->fresh()->toArray());
            }
            return $draft;
        });

        if ($created) $this->notifications->notifyFieldDraftSubmitted($draft);

        return $this->draftResult($draft, $uuid);
    }

    public function submitResident(User $user, array $input): array
    {
        $purok = $this->assignedPurok($user);
        $uuid = $input['mobile_uuid'] ?? null;
        $official = $this->officialResident($user, $input['id'] ?? null, $uuid);
        $household = $this->officialHousehold($user, $input['household_id'] ?? null,
            $input['household_mobile_uuid'] ?? null);

        if ($official) {
            if (! $household) throw new \RuntimeException('Choose a verified household in your assigned purok.');
            return $this->submitCorrection($user, $official, 'resident', $input,
                [...Arr::only($input, self::RESIDENT_FIELDS), 'household_id' => $household->id]);
        }

        if (! $uuid || isset($input['id'])) throw new \RuntimeException('Resident not found in your assigned purok.');

        $attributes = Arr::only($input, self::RESIDENT_FIELDS);
        foreach (['last_name', 'first_name', 'birth_date', 'birth_place', 'sex', 'civil_status',
            'citizenship', 'relationship_to_head', 'is_active'] as $field) {
            if (! array_key_exists($field, $attributes)) throw new \RuntimeException("New residents require '{$field}'.");
        }
        if (! $attributes['is_active']) throw new \RuntimeException('New resident submissions must be active.');

        $created = false;
        $residentDraft = DB::transaction(function () use ($user, $purok, $input, $uuid, $household, $attributes, &$created): ResidentDraft {
            $parent = null;
            if (! $household && ! empty($input['household_mobile_uuid']) && empty($input['household_id'])) {
                $parent = HouseholdDraft::query()->where('mobile_uuid', $input['household_mobile_uuid'])->lockForUpdate()->first();
                if (! $parent || (int) $parent->submitted_by_user_id !== (int) $user->id ||
                    (int) $parent->purok_id !== (int) $purok->id || $parent->draft_status === HouseholdDraft::STATUS_REJECTED) {
                    throw new \RuntimeException('The submitted household is not available for this resident.');
                }
                if ($parent->draft_status === HouseholdDraft::STATUS_APPROVED) {
                    $household = $parent->approvedHousehold;
                    $parent = null;
                }
            }
            if (! $parent && ! $household) throw new \RuntimeException('Resident household not found in the assigned purok.');

            $existing = ResidentDraft::query()->where('mobile_uuid', $uuid)->lockForUpdate()->first();
            if ($existing && (int) $existing->householdDraft?->submitted_by_user_id !== (int) $user->id) {
                throw new \RuntimeException('This resident submission belongs to another account.');
            }
            if ($existing && $existing->householdDraft?->draft_status !== HouseholdDraft::STATUS_PENDING) return $existing;

            if (! $parent && $household) {
                if ((int) $household->purok_id !== (int) $purok->id) throw new \RuntimeException('Household is outside your assigned purok.');
                $parent = $existing?->householdDraft;
                if (! $parent || $parent->target_household_id !== $household->id) {
                    $parent = HouseholdDraft::query()->create([
                        'submitted_by_user_id' => $user->id,
                        'barangay_id' => $purok->barangay_id,
                        'purok_id' => $purok->id,
                        'target_household_id' => $household->id,
                        'household_address' => $household->household_address,
                        'draft_status' => HouseholdDraft::STATUS_PENDING,
                    ]);
                    AuditLog::logMutation('created', $user, $parent);
                    $created = true;
                }
            }

            if (! $existing) {
                $existing = $parent->residentDrafts()->create([
                    ...Arr::except($attributes, ['is_active']),
                    'mobile_uuid' => $uuid,
                    'mobile_revision' => $input['local_revision'] ?? 0,
                    'is_household_head_candidate' => in_array($attributes['relationship_to_head'], ['Head', 'Head of Household'], true),
                ]);
                AuditLog::logMutation('created', $user, $existing);
                $created = true;
            } elseif (($input['local_revision'] ?? 0) > $existing->mobile_revision) {
                $old = $existing->toArray();
                $existing->update([
                    ...Arr::except($attributes, ['is_active']),
                    'household_draft_id' => $parent->id,
                    'mobile_revision' => $input['local_revision'],
                ]);
                AuditLog::logMutation('updated', $user, $existing, $old, $existing->fresh()->toArray());
            }
            return $existing;
        });

        if ($created) $this->notifications->notifyFieldDraftSubmitted($residentDraft->householdDraft);

        return $this->residentDraftResult($residentDraft, $uuid);
    }

    private function assignedPurok(User $user): Purok
    {
        $barangayId = MobileBarangayScope::requireBarangayId($user);
        $purok = Purok::query()->active()->whereKey($user->assigned_purok_id)
            ->where('barangay_id', $barangayId)->first();
        if (! $purok) throw new \RuntimeException('Your account needs an active assigned purok for registry submissions.');
        return $purok;
    }

    private function officialHousehold(User $user, ?int $id, ?string $uuid): ?Household
    {
        $query = Household::query()->where('purok_id', $user->assigned_purok_id);
        if ($id) {
            $record = (clone $query)->find($id);
            if (! $record || ($uuid && $record->mobile_uuid && $record->mobile_uuid !== $uuid)) {
                throw new \RuntimeException('Household not found in your assigned purok.');
            }
            return $record;
        }
        if ($uuid) {
            $record = Household::query()->where('mobile_uuid', $uuid)->first();
            if ($record && (int) $record->purok_id !== (int) $user->assigned_purok_id) {
                throw new \RuntimeException('Household not found in your assigned purok.');
            }
            return $record;
        }
        return null;
    }

    private function officialResident(User $user, ?int $id, ?string $uuid): ?Resident
    {
        $query = Resident::query()->whereHas('household', fn ($builder) =>
            $builder->where('purok_id', $user->assigned_purok_id));
        if ($id) {
            $record = (clone $query)->find($id);
            if (! $record || ($uuid && $record->mobile_uuid && $record->mobile_uuid !== $uuid)) {
                throw new \RuntimeException('Resident not found in your assigned purok.');
            }
            return $record;
        }
        if ($uuid) {
            $record = Resident::query()->where('mobile_uuid', $uuid)->first();
            if ($record && (int) $record->household?->purok_id !== (int) $user->assigned_purok_id) {
                throw new \RuntimeException('Resident not found in your assigned purok.');
            }
            return $record;
        }
        return null;
    }

    private function submitCorrection(User $user, Model $subject, string $type, array $input, array $proposed): array
    {
        $uuid = $input['mobile_uuid'] ?? $subject->mobile_uuid;
        if (! $uuid) throw new \RuntimeException('A mobile identifier is required for corrections.');
        $revision = $input['local_revision'] ?? 0;
        $key = "{$type}:{$uuid}:{$revision}";
        $request = ProfileUpdateRequest::query()->where('mobile_submission_key', $key)->first();
        if ($request && ((int) $request->submitted_by_user_id !== (int) $user->id ||
            (int) $request->subject_id !== (int) $subject->id || $request->subject_type !== $type)) {
            throw new \RuntimeException('This correction belongs to another account.');
        }

        if (! $request && $this->matchesOfficial($subject, $proposed)) {
            return $this->result($subject->id, $uuid, 'approved', 'unchanged', $subject->updated_at);
        }

        if (! $request) {
            $request = ProfileUpdateRequest::query()->create([
                'mobile_submission_key' => $key,
                'submitted_by_user_id' => $user->id,
                'barangay_id' => $user->assigned_barangay_id,
                'subject_type' => $type,
                'subject_id' => $subject->id,
                'current_snapshot' => $subject->toArray(),
                'proposed_changes' => $proposed,
                'request_reason' => 'Submitted from BHW mobile field correction.',
                'request_status' => ProfileUpdateRequest::STATUS_PENDING,
            ]);
            AuditLog::logMutation('created', $user, $request);
            $this->notifications->notifyProfileUpdateSubmitted($request);
        }

        return $this->result($subject->id, $uuid,
            $request->request_status === ProfileUpdateRequest::STATUS_PENDING ? 'submitted' : $request->request_status,
            'submitted', $request->updated_at, $request->review_notes);
    }

    private function matchesOfficial(Model $official, array $proposed): bool
    {
        foreach ($proposed as $field => $value) {
            $current = $official->{$field};
            if ($current instanceof \DateTimeInterface) $current = $current->format('Y-m-d');
            if ((string) $current !== (string) $value) return false;
        }
        return true;
    }

    private function draftResult(HouseholdDraft $draft, string $uuid): array
    {
        return $this->result($draft->approved_household_id, $uuid,
            $draft->draft_status === HouseholdDraft::STATUS_PENDING ? 'submitted' : $draft->draft_status,
            'submitted', $draft->updated_at, $draft->verification_notes);
    }

    private function residentDraftResult(ResidentDraft $draft, string $uuid): array
    {
        return $this->result($draft->approved_resident_id, $uuid,
            $draft->householdDraft->draft_status === HouseholdDraft::STATUS_PENDING ? 'submitted' : $draft->householdDraft->draft_status,
            'submitted', $draft->updated_at, $draft->householdDraft->verification_notes);
    }

    private function result(?int $id, string $uuid, string $status, string $operation, mixed $updatedAt, ?string $notes = null): array
    {
        return [
            'id' => $id,
            'mobile_uuid' => $uuid,
            'operation' => $operation,
            'verification_status' => $status,
            'verification_notes' => $notes,
            'updated_at' => $updatedAt?->toIso8601String(),
        ];
    }
}
