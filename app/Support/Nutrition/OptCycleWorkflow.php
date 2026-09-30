<?php

namespace App\Support\Nutrition;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\ChildNutritionProfile;
use App\Models\OptCycle;
use App\Models\OptCycleEntry;
use App\Models\OptMeasurement;
use App\Models\Resident;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class OptCycleWorkflow
{
    public function __construct(private readonly GrowthAssessmentService $assessment) {}

    public function barangayId(User $user): int
    {
        abort_unless($user->role === 'bns' && $user->is_active && $user->assigned_barangay_id, 403);

        return (int) $user->assigned_barangay_id;
    }

    public function authorize(User $user, OptCycle $cycle, ?OptCycleEntry $entry = null): void
    {
        abort_unless($cycle->barangay_id === $this->barangayId($user), 404);
        abort_if($entry && $entry->opt_cycle_id !== $cycle->id, 404);
    }

    private function writable(User $user, OptCycle $cycle): OptCycle
    {
        $this->authorize($user, $cycle);
        $cycle = OptCycle::query()->lockForUpdate()->findOrFail($cycle->id);
        if ($cycle->status !== OptCycle::IN_PROGRESS) {
            throw ValidationException::withMessages(['cycle' => 'Reopen this completed cycle before making changes.']);
        }

        return $cycle;
    }

    public function create(User $user, array $data): OptCycle
    {
        $barangayId = $this->barangayId($user);
        $data = Validator::make($data, OptCycleRules::cycleValidationRules())
            ->after(OptCycleRules::validateReference(...))->validate();
        $reference = CarbonImmutable::parse($data['reference_date'])->startOfDay();
        try {
            return DB::transaction(function () use ($user, $data, $barangayId, $reference): OptCycle {
                $barangay = Barangay::query()->findOrFail($barangayId);
                $cycle = OptCycle::query()->create([
                    'barangay_id' => $barangayId, 'year' => $data['year'], 'round' => $data['round'],
                    'reference_date' => $reference, 'roster_captured_at' => now(), 'status' => OptCycle::IN_PROGRESS,
                    'created_by_user_id' => $user->id, 'rules_version' => OptCycleRules::VERSION,
                    'provenance' => ['registry_as_of' => now()->toIso8601String(), 'historical_population_reconstructed' => false],
                ]);
                $residents = OptCycleRules::residents($barangayId, $reference)
                    ->with(['household.purok', 'socioEconomicProfile', 'childNutritionProfile'])
                    ->orderBy('id')->get();
                foreach ($residents as $resident) {
                    if (! OptCycleRules::eligibleDob($resident->getRawOriginal('birth_date'), $reference)) {
                        continue;
                    }
                    $household = $resident->household;
                    $profile = $resident->childNutritionProfile;
                    $confirmedIp = $profile?->ip_confirmed_at && $profile->ip_membership !== null;
                    $cycle->entries()->create([
                        'resident_id' => $resident->id, 'resident_key' => $resident->id,
                        'resident_code' => $resident->official_resident_code,
                        'first_name' => $resident->first_name, 'middle_name' => $resident->middle_name,
                        'last_name' => $resident->last_name, 'suffix' => $resident->suffix,
                        'birth_date' => $resident->birth_date, 'sex' => $resident->sex,
                        'barangay_name' => $barangay->name, 'municipality' => $barangay->municipality,
                        'province' => $barangay->province, 'region' => $barangay->region, 'psgc_code' => $barangay->psgc_code,
                        'purok_key' => $household->purok_id, 'purok_name' => $household->purok->display_name,
                        'household_code' => $household->official_household_code, 'household_number' => $household->household_no,
                        'address' => $household->household_address,
                        'caregiver_resident_key' => $profile?->caregiver_resident_id,
                        'caregiver_name' => $profile?->caregiver_name, 'caregiver_relationship' => $profile?->caregiver_relationship,
                        'caregiver_confirmed_at' => $profile?->caregiver_confirmed_at,
                        'ip_membership' => $confirmedIp ? $profile->ip_membership : null,
                        'ip_confirmed_at' => $confirmedIp ? $profile->ip_confirmed_at : null,
                        'ethnicity' => $resident->socioEconomicProfile?->ethnicity,
                    ]);
                }
                AuditLog::logMutation('created', $user, $cycle, null, ['eligible_children' => $cycle->entries()->count(), 'rules_version' => $cycle->rules_version]);

                return $cycle;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (OptCycle::where('barangay_id', $barangayId)->where('year', $data['year'])->where('round', $data['round'])->exists()) {
                throw ValidationException::withMessages(['round' => 'This barangay already has this OPT+ round for the selected year.']);
            }
            throw $exception;
        }
    }

    public function measure(User $user, OptCycle $cycle, OptCycleEntry $entry, array $data): OptMeasurement
    {
        $this->authorize($user, $cycle, $entry);
        $data = Validator::make($data, OptCycleRules::measurementValidationRules() + [
            'caregiver_name' => ['nullable', 'string', 'max:255'], 'caregiver_resident_id' => ['nullable', 'integer'],
        ])->validate();

        return DB::transaction(function () use ($user, $cycle, $entry, $data): OptMeasurement {
            $this->writable($user, $cycle);
            $entry = OptCycleEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $date = CarbonImmutable::parse($data['measurement_date']);
            if ($date->lt($entry->birth_date) || $date->isFuture()) {
                throw ValidationException::withMessages(['measurement_date' => 'Use a measurement date from birth through today.']);
            }
            if (array_key_exists('caregiver_name', $data) || ! empty($data['caregiver_resident_id'])) {
                $this->saveMeasurementCaregiver($user, $cycle, $entry, $data);
            }
            $values = [
                'resident_id' => $entry->resident_id, 'barangay_id' => $cycle->barangay_id,
                'measured_by_user_id' => $user->id, 'measurement_date' => $date,
                'weight_kg' => $data['weight_kg'], 'height_cm' => $data['height_cm'],
                'measurement_posture' => $data['measurement_posture'], 'remarks' => $data['remarks'] ?? null,
                'age_in_months' => OptCycleRules::ageMonths($entry->birth_date, $date), 'sex_snapshot' => $entry->sex,
                'assessment_source' => 'internal_who_reference', 'assessment_version' => 'who-lms-v1', 'assessment_error' => null,
                'weight_for_age_z_score' => null, 'weight_for_age_status' => null,
                'height_for_age_z_score' => null, 'height_for_age_status' => null,
                'weight_for_length_height_z_score' => null, 'weight_for_length_height_status' => null,
            ];
            try {
                $snapshot = new Resident(['birth_date' => $entry->birth_date, 'sex' => $entry->sex]);
                $assessment = $this->assessment->assess($snapshot, $date, (float) $data['weight_kg'], (float) $data['height_cm'], $data['measurement_posture']);
                foreach (array_keys($values) as $key) {
                    if (array_key_exists($key, $assessment)) {
                        $values[$key] = $assessment[$key];
                    }
                }
            } catch (\Throwable $exception) {
                if (! $exception instanceof InvalidArgumentException) {
                    report($exception);
                }
                $values['assessment_error'] = 'Internal reference assessment unavailable; raw measurements retained.';
            }
            $measurement = $entry->measurement()->first();
            $old = $measurement?->toArray();
            if ($measurement) {
                $measurement->update($values);
            } else {
                $measurement = $entry->measurement()->create($values);
            }
            AuditLog::logMutation($old ? 'updated' : 'created', $user, $measurement, $old, $measurement->fresh()->toArray());

            return $measurement;
        });
    }

    private function caregiverValues(OptCycle $cycle, OptCycleEntry $entry, array $data): array
    {
        $caregiver = empty($data['caregiver_resident_id']) ? null : Resident::query()
            ->where('is_active', true)->where('resident_status', Resident::STATUS_ACTIVE)
            ->whereHas('household.purok', fn ($q) => $q->where('barangay_id', $cycle->barangay_id))
            ->findOrFail($data['caregiver_resident_id']);
        if ($caregiver && $caregiver->id === $entry->resident_key) {
            throw ValidationException::withMessages(['caregiver_name' => 'Please choose the mother or caregiver, not the child.']);
        }

        return ['caregiver_resident_id' => $caregiver?->id,
            'caregiver_name' => $caregiver?->full_name ?? (trim($data['caregiver_name'] ?? '') ?: null)];
    }

    private function saveMeasurementCaregiver(User $user, OptCycle $cycle, OptCycleEntry $entry, array $data): void
    {
        $resident = Resident::query()->whereKey($entry->resident_id)
            ->whereHas('household.purok', fn ($q) => $q->where('barangay_id', $cycle->barangay_id))->firstOrFail();
        $values = $this->caregiverValues($cycle, $entry, $data);
        $profile = ChildNutritionProfile::firstOrNew(['resident_id' => $resident->id]);
        $old = $profile->exists ? $profile->toArray() : null;
        if ($profile->exists && $profile->caregiver_resident_id === $values['caregiver_resident_id']
            && ($data['caregiver_name'] ?? '') === $profile->caregiver_name) {
            $values['caregiver_name'] = $profile->caregiver_name;
        }
        $profile->fill($values);
        if (! $profile->exists || $profile->isDirty(['caregiver_name', 'caregiver_resident_id'])) {
            $profile->caregiver_relationship = null;
            $profile->caregiver_confirmed_at = $values['caregiver_name'] ? now() : null;
            $profile->updated_by_user_id = $user->id;
            $profile->save();
            AuditLog::logMutation($old ? 'updated' : 'created', $user, $profile, $old, $profile->toArray());
        }
        // First weighing can record a missing caregiver, never overwrite captured names.
        if (! $entry->caregiver_name && ! $entry->measurement()->exists() && $values['caregiver_name']) {
            $oldEntry = $entry->toArray();
            $entry->update(['caregiver_resident_key' => $profile->caregiver_resident_id,
                'caregiver_name' => $profile->caregiver_name, 'caregiver_relationship' => $profile->caregiver_relationship,
                'caregiver_confirmed_at' => $profile->caregiver_confirmed_at]);
            AuditLog::logMutation('updated', $user, $entry, $oldEntry, $entry->toArray() + ['reason' => 'Caregiver first recorded during initial weighing.']);
        }
    }

    public function correctHistoricalInformation(User $user, OptCycle $cycle, OptCycleEntry $entry, array $data): void
    {
        $this->authorize($user, $cycle, $entry);
        $data = Validator::make($data, [
            'caregiver_name' => ['nullable', 'string', 'max:255'], 'caregiver_resident_id' => ['nullable', 'integer'],
            'ip_membership' => ['required', 'in:yes,no,unknown'], 'correction_reason' => ['required', 'string', 'max:1500'],
        ])->validate();
        if (! trim($data['correction_reason'])) {
            throw ValidationException::withMessages(['correction_reason' => 'Please explain the correction.']);
        }
        DB::transaction(function () use ($user, $cycle, $entry, $data): void {
            $this->writable($user, $cycle);
            $entry = OptCycleEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $values = $this->caregiverValues($cycle, $entry, $data);
            $old = $entry->toArray();
            $entry->update(['caregiver_resident_key' => $values['caregiver_resident_id'], 'caregiver_name' => $values['caregiver_name'],
                'caregiver_relationship' => $entry->caregiver_name === $values['caregiver_name'] && $entry->caregiver_resident_key === $values['caregiver_resident_id'] ? $entry->caregiver_relationship : null,
                'caregiver_confirmed_at' => $values['caregiver_name'] ? now() : null,
                'ip_membership' => match ($data['ip_membership']) {
                    'yes' => true, 'no' => false, default => null
                },
                'ip_confirmed_at' => $data['ip_membership'] !== 'unknown' ? now() : null]);
            AuditLog::logMutation('updated', $user, $entry, $old, $entry->toArray() + ['correction_reason' => $data['correction_reason']]);
        });
    }

    public function confirmProfile(User $user, OptCycle $cycle, OptCycleEntry $entry, array $data): void
    {
        $this->authorize($user, $cycle, $entry);
        DB::transaction(function () use ($user, $cycle, $entry, $data): void {
            $this->writable($user, $cycle);
            $entry = OptCycleEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $resident = Resident::query()->whereKey($entry->resident_id)
                ->whereHas('household.purok', fn ($q) => $q->where('barangay_id', $cycle->barangay_id))->firstOrFail();
            $caregiver = empty($data['caregiver_resident_id']) ? null : Resident::query()
                ->whereHas('household.purok', fn ($q) => $q->where('barangay_id', $cycle->barangay_id))
                ->findOrFail($data['caregiver_resident_id']);
            if ($caregiver?->id === $resident->id) {
                throw ValidationException::withMessages(['caregiver_resident_id' => 'The child cannot be their own caregiver.']);
            }
            $name = $caregiver?->full_name ?? trim($data['caregiver_name'] ?? '');
            $profile = ChildNutritionProfile::firstOrNew(['resident_id' => $resident->id]);
            $old = $profile->exists ? $profile->toArray() : null;
            $profile->fill([
                'caregiver_resident_id' => $caregiver?->id, 'caregiver_name' => $name ?: null,
                'caregiver_relationship' => $data['caregiver_relationship'] ?? null,
                'caregiver_confirmed_at' => $name ? now() : null,
                'ip_membership' => match ($data['ip_membership'] ?? 'unknown') {
                    'yes' => true, 'no' => false, default => null
                },
                'ip_confirmed_at' => in_array($data['ip_membership'] ?? '', ['yes', 'no'], true) ? now() : null,
                'updated_by_user_id' => $user->id,
            ])->save();
            AuditLog::logMutation($old ? 'updated' : 'created', $user, $profile, $old, $profile->toArray());
            if (! empty($data['update_cycle_snapshot'])) {
                if (empty(trim($data['correction_reason'] ?? ''))) {
                    throw ValidationException::withMessages(['correction_reason' => 'Explain the explicit snapshot correction.']);
                }
                $oldEntry = $entry->toArray();
                $entry->update([
                    'caregiver_resident_key' => $profile->caregiver_resident_id, 'caregiver_name' => $profile->caregiver_name,
                    'caregiver_relationship' => $profile->caregiver_relationship, 'caregiver_confirmed_at' => $profile->caregiver_confirmed_at,
                    'ip_membership' => $profile->ip_membership, 'ip_confirmed_at' => $profile->ip_confirmed_at,
                ]);
                AuditLog::logMutation('updated', $user, $entry, $oldEntry, $entry->toArray() + ['correction_reason' => $data['correction_reason']]);
            }
        });
    }

    public function complete(User $user, OptCycle $cycle, bool $confirmed, ?string $note): void
    {
        DB::transaction(function () use ($user, $cycle, $confirmed, $note): void {
            $cycle = $this->writable($user, $cycle);
            if ($cycle->entries()->whereDoesntHave('measurement')->exists() && (! $confirmed || ! trim($note ?? ''))) {
                throw ValidationException::withMessages(['completion_note' => 'Confirm incomplete coverage and explain why the cycle is being completed.']);
            }
            $old = $cycle->toArray();
            $cycle->update(['status' => OptCycle::COMPLETED, 'completed_at' => now(), 'completed_by_user_id' => $user->id, 'completion_note' => $note]);
            AuditLog::logMutation('updated', $user, $cycle, $old, $cycle->toArray());
        });
    }

    public function reopen(User $user, OptCycle $cycle, string $reason): void
    {
        $this->authorize($user, $cycle);
        DB::transaction(function () use ($user, $cycle, $reason): void {
            $cycle = OptCycle::query()->lockForUpdate()->findOrFail($cycle->id);
            if ($cycle->status !== OptCycle::COMPLETED || ! trim($reason)) {
                throw ValidationException::withMessages(['reopening_reason' => 'Only completed cycles can be reopened, with a reason.']);
            }
            $old = $cycle->toArray();
            $cycle->update(['status' => OptCycle::IN_PROGRESS, 'reopened_at' => now(), 'reopened_by_user_id' => $user->id, 'reopening_reason' => $reason]);
            AuditLog::logMutation('updated', $user, $cycle, $old, $cycle->toArray());
        });
    }
}
