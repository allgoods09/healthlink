<?php

namespace App\Support;

use App\Models\FieldVisit;
use App\Models\Household;
use App\Models\PhilpenRiskAssessment;
use App\Models\Resident;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileSyncProcessor
{
    public function __construct(
        private readonly RoleNotificationService $roleNotificationService,
        private readonly MobileRegistrySubmissionService $registrySubmissions
    ) {
    }

    /**
     * Process a mobile sync payload and return a structured result.
     */
    public function process(User $user, array $payload): array
    {
        MobileBarangayScope::requirePurok($user);
        $households = array_values($payload['households'] ?? []);
        $residents = array_values($payload['residents'] ?? []);
        $fieldVisits = array_values($payload['field_visits'] ?? []);
        $riskAssessments = array_values($payload['risk_assessments'] ?? []);
        $recordsSynced = 0;
        $failures = [];
        $resolvedRecords = [
            'households' => [],
            'residents' => [],
            'field_visits' => [],
            'risk_assessments' => [],
        ];
        $summary = [
            'households_received' => count($households),
            'residents_received' => count($residents),
            'field_visits_received' => count($fieldVisits),
            'risk_assessments_received' => count($riskAssessments),
            'households_synced' => 0,
            'residents_synced' => 0,
            'field_visits_synced' => 0,
            'risk_assessments_synced' => 0,
            'failure_count' => 0,
        ];

        foreach ($households as $index => $record) {
            $result = $this->syncHousehold($user, $record, $index);

            if ($result['success']) {
                $recordsSynced++;
                $summary['households_synced']++;
                $resolvedRecords['households'][] = $result['record'];

                continue;
            }

            $failures[] = $result['failure'];
        }

        foreach ($residents as $index => $record) {
            $result = $this->syncResident($user, $record, $index);

            if ($result['success']) {
                $recordsSynced++;
                $summary['residents_synced']++;
                $resolvedRecords['residents'][] = $result['record'];

                continue;
            }

            $failures[] = $result['failure'];
        }

        foreach ($fieldVisits as $index => $record) {
            $result = $this->syncFieldVisit($user, $record, $index);

            if ($result['success']) {
                $recordsSynced++;
                $summary['field_visits_synced']++;
                $resolvedRecords['field_visits'][] = $result['record'];

                continue;
            }

            $failures[] = $result['failure'];
        }

        foreach ($riskAssessments as $index => $record) {
            $result = $this->syncRiskAssessment($user, $record, $index);

            if ($result['success']) {
                $recordsSynced++;
                $summary['risk_assessments_synced']++;
                $resolvedRecords['risk_assessments'][] = $result['record'];

                continue;
            }

            $failures[] = $result['failure'];
        }

        $summary['failure_count'] = count($failures);
        $summary['failed_records'] = array_slice($failures, 0, 10);
        $totalRecords = count($households) + count($residents) + count($fieldVisits) + count($riskAssessments);
        $status = $this->determineStatus($recordsSynced, $totalRecords, count($failures));

        return [
            'records_synced' => $recordsSynced,
            'status' => $status,
            'summary' => $summary,
            'failures' => $failures,
            'resolved_records' => $resolvedRecords,
            'error_message' => $this->errorMessageFor($status, $failures),
        ];
    }

    private function syncHousehold(User $user, mixed $record, int $index): array
    {
        try {
            return ['success' => true, 'record' => $this->registrySubmissions->submitHousehold(
                $user, $this->validateHouseholdRecord($record)
            )];
        } catch (ValidationException $exception) {
            return $this->failure('households', $index, $exception->validator->errors()->first());
        } catch (\Throwable $exception) {
            return $this->failure('households', $index, $exception->getMessage());
        }
    }

    private function syncResident(User $user, mixed $record, int $index): array
    {
        try {
            return ['success' => true, 'record' => $this->registrySubmissions->submitResident(
                $user, $this->validateResidentRecord($record)
            )];
        } catch (ValidationException $exception) {
            return $this->failure('residents', $index, $exception->validator->errors()->first());
        } catch (\Throwable $exception) {
            return $this->failure('residents', $index, $exception->getMessage());
        }
    }

    /**
     * Sync a single field visit payload.
     */
    private function syncFieldVisit(User $user, mixed $record, int $index): array
    {
        try {
            $validated = $this->validateFieldVisitRecord($record);
        } catch (ValidationException $exception) {
            return $this->failure('field_visits', $index, $exception->validator->errors()->first());
        }

        $household = $this->resolveHouseholdForUser(
            $user,
            $validated['household_id'] ?? null,
            $validated['household_mobile_uuid'] ?? null
        );

        if (! $household) {
            return $this->failure('field_visits', $index, 'Visit household not found in the assigned purok.');
        }

        $visit = $this->resolveFieldVisitForUser(
            $user,
            $validated['id'] ?? null,
            $validated['mobile_uuid'] ?? null
        );

        $creating = ! $visit;

        if (isset($validated['id']) && ! $visit) {
            return $this->failure('field_visits', $index, 'Visit identity not found or does not match in the assigned purok.');
        }

        if (! $visit && empty($validated['mobile_uuid'])) {
            return $this->failure('field_visits', $index, 'New field visits require a mobile UUID.');
        }

        if (! $visit) {
            $visit = new FieldVisit([
                'mobile_uuid' => $validated['mobile_uuid'],
                'source' => 'mobile',
            ]);
        }

        $creating = ! $visit->exists;
        $currentPhotos = collect($visit->photos ?? []);
        $retainPaths = array_key_exists('existing_photos', $validated)
            ? $validated['existing_photos']
            : $currentPhotos->pluck('path')->filter()->values()->all();

        $invalidRetainedPhoto = collect($retainPaths)->contains(function (string $path) use ($currentPhotos): bool {
            return ! $currentPhotos->contains(fn (array $photo) => ($photo['path'] ?? null) === $path);
        });

        if ($invalidRetainedPhoto) {
            return $this->failure('field_visits', $index, 'One or more retained photos are not attached to this visit.');
        }

        $storedPhotos = [];

        try {
            $storedPhotos = $this->storeVisitPhotos($validated['photos'] ?? []);
        } catch (\Throwable $exception) {
            return $this->failure('field_visits', $index, 'Visit photo upload failed. Please retry.');
        }

        $retainedPhotos = $currentPhotos
            ->filter(fn (array $photo) => in_array($photo['path'] ?? null, $retainPaths, true))
            ->values()
            ->all();
        $deletedPhotos = $currentPhotos
            ->reject(fn (array $photo) => in_array($photo['path'] ?? null, $retainPaths, true))
            ->values()
            ->all();
        $attributes = [];

        foreach (['mobile_uuid', 'visited_at', 'notes'] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = $validated[$field];
            }
        }

        if ($creating && ! array_key_exists('visited_at', $attributes)) {
            $this->deleteVisitPhotos($storedPhotos);

            return $this->failure('field_visits', $index, "New field visits require the 'visited_at' field.");
        }

        if (! $creating && $attributes === [] && $storedPhotos === [] && $retainPaths === $currentPhotos->pluck('path')->filter()->values()->all()) {
            return $this->failure('field_visits', $index, 'No updatable field visit data was provided.');
        }

        try {
            DB::transaction(function () use ($visit, $attributes, $household, $user, $creating, $retainedPhotos, $storedPhotos): void {
                $visit->fill(array_merge($attributes, [
                    'household_id' => $household->id,
                    'photos' => array_values([...$retainedPhotos, ...$storedPhotos]),
                    'source' => 'mobile',
                    'last_synced_at' => now(),
                ]));

                if ($creating) {
                    $visit->recorded_by_user_id = $user->id;
                }

                $visit->save();
            });
        } catch (\Throwable $exception) {
            $this->deleteVisitPhotos($storedPhotos);

            return $this->failure('field_visits', $index, 'Field visit update failed. Please retry.');
        }

        $this->deleteVisitPhotos($deletedPhotos);

        return [
            'success' => true,
            'record' => [
                'id' => $visit->id,
                'mobile_uuid' => $visit->mobile_uuid,
                'household_id' => $visit->household_id,
                'operation' => $creating ? 'created' : 'updated',
                'updated_at' => optional($visit->updated_at)->toIso8601String(),
            ],
        ];
    }

    /**
     * Sync a single PhilPEN risk assessment payload.
     */
    private function syncRiskAssessment(User $user, mixed $record, int $index): array
    {
        try {
            $validated = $this->validateRiskAssessmentRecord($record);
        } catch (ValidationException $exception) {
            return $this->failure('risk_assessments', $index, $exception->validator->errors()->first());
        }

        $resident = $this->resolveResidentForBarangay(
            $user,
            $validated['resident_id'] ?? null
        );

        if (! $resident) {
            return $this->failure('risk_assessments', $index, 'Resident not found in the assigned barangay.');
        }

        $assessment = $this->resolveRiskAssessmentForUser(
            $user,
            $validated['id'] ?? null,
            $validated['mobile_uuid'] ?? null
        );

        $creating = ! $assessment;

        if (! $assessment && empty($validated['mobile_uuid'])) {
            return $this->failure('risk_assessments', $index, 'New risk assessments require a mobile UUID.');
        }

        if (! $assessment) {
            $assessment = new PhilpenRiskAssessment([
                'mobile_uuid' => $validated['mobile_uuid'],
                'source' => 'mobile',
            ]);
        }

        $assessmentDate = $validated['assessment_date'] ?? optional($assessment->assessment_date)?->toDateString();

        if (! $assessmentDate) {
            return $this->failure('risk_assessments', $index, "New risk assessments require the 'assessment_date' field.");
        }

        $redFlags = $validated['red_flags'] ?? ($assessment->red_flags ?? []);
        $requiresImmediateReferral = $this->requiresImmediateReferral($redFlags);
        $bodyMassIndex = $this->calculateBodyMassIndex(
            $validated['weight_kg'] ?? $assessment->weight_kg,
            $validated['height_cm'] ?? $assessment->height_cm
        );
        $ageYears = $resident->birth_date
            ? (int) $resident->birth_date->diffInYears($assessmentDate)
            : null;

        $attributes = [];

        foreach ([
            'mobile_uuid',
            'assessment_date',
            'religion',
            'contact_number',
            'philhealth_number',
            'civil_status',
            'ethnicity',
            'pwd_id_number',
            'weight_kg',
            'height_cm',
            'waist_circumference_cm',
            'systolic_bp',
            'diastolic_bp',
            'employment_status',
            'ip_classification',
            'red_flags',
            'past_medical_history',
            'family_history',
            'tobacco_use',
            'alcohol_consumption_status',
            'alcohol_binge_flag',
            'physical_activity_met',
            'high_risk_diet_weekly',
            'blood_sugar_notes',
            'fbs_result',
            'rbs_result',
            'dm_symptoms',
            'lipid_profile_date',
            'total_cholesterol',
            'hdl',
            'ldl',
            'vldl',
            'triglycerides',
            'urinalysis_protein',
            'urinalysis_ketones',
            'urinalysis_date',
            'chronic_respiratory_symptoms',
            'lifestyle_modification',
            'anti_hypertensive_medications',
            'oral_hypoglycemic_medications',
            'follow_up_date',
            'remarks',
        ] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = $validated[$field];
            }
        }

        $attributes['resident_id'] = $resident->id;
        $attributes['household_id'] = $resident->household_id;
        $attributes['barangay_id'] = $resident->household?->purok?->barangay_id;
        $attributes['purok_id'] = $resident->household?->purok_id;
        $attributes['recorded_by_user_id'] = $user->id;
        $attributes['source'] = 'mobile';
        $attributes['age_years'] = $ageYears;
        $attributes['body_mass_index'] = $bodyMassIndex;
        $attributes['requires_immediate_referral'] = $requiresImmediateReferral;
        $attributes['identity_snapshot'] = $this->identitySnapshotFor($resident, $user, $assessmentDate, $validated);
        $attributes['last_synced_at'] = now();

        $comparisonAttributes = $attributes;
        unset($comparisonAttributes['last_synced_at']);

        if (! $creating) {
            if ($this->riskAssessmentMatchesExisting($assessment, $comparisonAttributes)) {
                return [
                    'success' => true,
                    'record' => [
                        'id' => $assessment->id,
                        'mobile_uuid' => $assessment->mobile_uuid,
                        'resident_id' => $assessment->resident_id,
                        'operation' => 'unchanged',
                        'updated_at' => optional($assessment->updated_at)->toIso8601String(),
                    ],
                ];
            }

            return $this->failure(
                'risk_assessments',
                $index,
                'PhilPEN assessments are historical records. Create a new assessment entry instead of editing an existing one.'
            );
        }

        if ($ageYears === null || $ageYears < 20) {
            return $this->failure(
                'risk_assessments',
                $index,
                'PhilPEN assessments are only available for residents aged 20 years old and above on the assessment date.'
            );
        }

        try {
            DB::transaction(function () use ($assessment, $attributes): void {
                $assessment->fill($attributes);
                $assessment->save();
            });
        } catch (\Throwable $exception) {
            return $this->failure('risk_assessments', $index, 'Risk assessment update failed: '.$exception->getMessage());
        }

        $this->roleNotificationService->notifyImmediateReferralRiskAssessment(
            $assessment->fresh('resident.household.purok.barangay'),
            $user
        );

        return [
            'success' => true,
            'record' => [
                'id' => $assessment->id,
                'mobile_uuid' => $assessment->mobile_uuid,
                'resident_id' => $assessment->resident_id,
                'operation' => $creating ? 'created' : 'updated',
                'updated_at' => optional($assessment->updated_at)->toIso8601String(),
            ],
        ];
    }

    /**
     * Validate a mobile household record.
     */
    private function validateHouseholdRecord(mixed $record): array
    {
        if (is_array($record) && isset($record['proposed_changes']) && array_diff(array_keys($record), [
            'id', 'mobile_uuid', 'local_revision', 'request_contract_version', 'base_snapshot', 'proposed_changes',
        ])) {
            throw ValidationException::withMessages(['household' => 'Unexpected household correction fields.']);
        }
        return Validator::make(is_array($record) ? $record : [], [
            'barangay_id' => ['prohibited'],
            'purok_id' => ['prohibited'],
            'submitted_by_user_id' => ['prohibited'],
            'approval_state' => ['prohibited'],
            'id' => ['nullable', 'integer'],
            'mobile_uuid' => ['nullable', 'uuid', 'required_without:id'],
            'household_no' => ['sometimes', 'required', 'string', 'max:50'],
            'household_address' => ['sometimes', 'required', 'string'],
            'is_social_aid_beneficiary' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'local_revision' => ['nullable', 'integer', 'min:0'],
            'request_contract_version' => ['sometimes', 'integer', 'in:1'],
            'base_snapshot' => ['required_with:proposed_changes', 'array:purok_id,household_no,household_address,is_social_aid_beneficiary'],
            'base_snapshot.purok_id' => ['required_with:proposed_changes', 'integer', 'min:1'],
            'base_snapshot.household_no' => ['required_with:proposed_changes', 'string', 'max:50'],
            'base_snapshot.household_address' => ['required_with:proposed_changes', 'string'],
            'base_snapshot.is_social_aid_beneficiary' => ['required_with:proposed_changes', 'boolean'],
            'proposed_changes' => ['sometimes', 'array:household_no,household_address,is_social_aid_beneficiary', 'min:1'],
            'proposed_changes.household_no' => ['sometimes', 'required', 'string', 'max:50'],
            'proposed_changes.household_address' => ['sometimes', 'required', 'string'],
            'proposed_changes.is_social_aid_beneficiary' => ['sometimes', 'required', 'boolean'],
        ])->validate();
    }

    /**
     * Validate a mobile resident record.
     */
    private function validateResidentRecord(mixed $record): array
    {
        return Validator::make(is_array($record) ? $record : [], [
            'barangay_id' => ['prohibited'],
            'purok_id' => ['prohibited'],
            'submitted_by_user_id' => ['prohibited'],
            'approval_state' => ['prohibited'],
            'id' => ['nullable', 'integer'],
            'mobile_uuid' => ['nullable', 'uuid', 'required_without:id'],
            'household_id' => ['nullable', 'integer'],
            'household_mobile_uuid' => ['nullable', 'uuid', 'required_without:household_id'],
            'philsys_card_no' => ['nullable', 'string', 'max:50'],
            ...ResidentProfileData::rules(),
            ...ResidentProfileData::rules('proposed_changes.'),
            'proposed_changes.philsys_card_no' => ['sometimes', 'nullable', 'string', 'max:50'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['sometimes', 'date_format:Y-m-d', 'before_or_equal:today'],
            'birth_place' => ['sometimes', 'string', 'max:255'],
            'sex' => ['sometimes', 'in:Male,Female'],
            'civil_status' => ['sometimes', 'string', 'max:50'],
            'citizenship' => ['sometimes', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'email_address' => ['nullable', 'email', 'max:100'],
            'relationship_to_head' => ['sometimes', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'local_revision' => ['nullable', 'integer', 'min:0'],
            'request_contract_version' => ['sometimes', 'integer', 'in:2'],
            'propose_household_head' => ['sometimes', 'boolean'],
            'base_snapshot' => ['required_with:proposed_changes', 'array'],
            'proposed_changes' => ['sometimes', 'array:'.implode(',', MobileResidentRequestData::EDITABLE)],
            'proposed_changes.household_id' => ['sometimes', 'required', 'integer'],
            'proposed_changes.first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'proposed_changes.last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'proposed_changes.middle_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'proposed_changes.suffix' => ['sometimes', 'nullable', 'string', 'max:20'],
            'proposed_changes.birth_date' => ['sometimes', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'proposed_changes.birth_place' => ['sometimes', 'required', 'string', 'max:255'],
            'proposed_changes.sex' => ['sometimes', 'required', 'in:Male,Female'],
            'proposed_changes.civil_status' => ['sometimes', 'required', 'string', 'max:50'],
            'proposed_changes.citizenship' => ['sometimes', 'required', 'string', 'max:100'],
            'proposed_changes.religion' => ['sometimes', 'nullable', 'string', 'max:100'],
            'proposed_changes.contact_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'proposed_changes.email_address' => ['sometimes', 'nullable', 'email', 'max:100'],
            'proposed_changes.relationship_to_head' => ['sometimes', 'required', 'string', 'max:100'],
        ])->validate();
    }

    /**
     * Validate a mobile field visit record.
     */
    private function validateFieldVisitRecord(mixed $record): array
    {
        return Validator::make(is_array($record) ? $record : [], [
            'id' => ['nullable', 'integer'],
            'mobile_uuid' => ['nullable', 'uuid', 'required_without:id'],
            'household_id' => ['nullable', 'integer'],
            'household_mobile_uuid' => ['nullable', 'uuid', 'required_without:household_id'],
            'visited_at' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string'],
            'existing_photos' => ['sometimes', 'array', 'max:5'],
            'existing_photos.*' => ['string'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*.file_name' => ['nullable', 'string', 'max:255'],
            'photos.*.mime_type' => ['required_with:photos', 'string', 'max:100'],
            'photos.*.captured_at' => ['nullable', 'date'],
            'photos.*.data' => ['required_with:photos', 'string'],
        ])->validate();
    }

    /**
     * Validate a mobile PhilPEN risk assessment record.
     */
    private function validateRiskAssessmentRecord(mixed $record): array
    {
        return Validator::make(is_array($record) ? $record : [], [
            'id' => ['nullable', 'integer'],
            'mobile_uuid' => ['nullable', 'uuid', 'required_without:id'],
            'resident_id' => ['required', 'integer'],
            'assessment_date' => ['sometimes', 'date'],
            'religion' => ['nullable', 'string', 'max:100'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'philhealth_number' => ['nullable', 'string', 'max:100'],
            'civil_status' => ['nullable', 'string', 'max:50'],
            'ethnicity' => ['nullable', 'string', 'max:100'],
            'pwd_id_number' => ['nullable', 'string', 'max:100'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'height_cm' => ['nullable', 'numeric', 'min:0'],
            'waist_circumference_cm' => ['nullable', 'numeric', 'min:0'],
            'systolic_bp' => ['nullable', 'integer', 'min:0'],
            'diastolic_bp' => ['nullable', 'integer', 'min:0'],
            'employment_status' => ['nullable', 'string', 'max:50'],
            'ip_classification' => ['nullable', 'in:ip,non_ip'],
            'red_flags' => ['nullable', 'array'],
            'past_medical_history' => ['nullable', 'array'],
            'family_history' => ['nullable', 'array'],
            'tobacco_use' => ['nullable', 'string', 'max:50'],
            'alcohol_consumption_status' => ['nullable', 'string', 'max:50'],
            'alcohol_binge_flag' => ['nullable', 'boolean'],
            'physical_activity_met' => ['nullable', 'boolean'],
            'high_risk_diet_weekly' => ['nullable', 'boolean'],
            'blood_sugar_notes' => ['nullable', 'string', 'max:120'],
            'fbs_result' => ['nullable', 'string', 'max:50'],
            'rbs_result' => ['nullable', 'string', 'max:50'],
            'dm_symptoms' => ['nullable', 'array'],
            'lipid_profile_date' => ['nullable', 'date'],
            'total_cholesterol' => ['nullable', 'string', 'max:50'],
            'hdl' => ['nullable', 'string', 'max:50'],
            'ldl' => ['nullable', 'string', 'max:50'],
            'vldl' => ['nullable', 'string', 'max:50'],
            'triglycerides' => ['nullable', 'string', 'max:50'],
            'urinalysis_protein' => ['nullable', 'string', 'max:50'],
            'urinalysis_ketones' => ['nullable', 'string', 'max:50'],
            'urinalysis_date' => ['nullable', 'date'],
            'chronic_respiratory_symptoms' => ['nullable', 'array'],
            'lifestyle_modification' => ['nullable', 'boolean'],
            'anti_hypertensive_medications' => ['nullable', 'string'],
            'oral_hypoglycemic_medications' => ['nullable', 'string'],
            'follow_up_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
        ])->validate();
    }

    /**
     * Resolve a household for the assigned purok by server ID or mobile UUID.
     */
    private function resolveHouseholdForUser(User $user, ?int $id, ?string $mobileUuid): ?Household
    {
        if ($id !== null) {
            $household = Household::query()
                ->whereKey($id)
                ->where('purok_id', $user->assigned_purok_id)
                ->first();

            return $household && (! $mobileUuid || $household->mobile_uuid === $mobileUuid) ? $household : null;
        }

        if ($mobileUuid) {
            return Household::query()
                ->where('mobile_uuid', $mobileUuid)
                ->where('purok_id', $user->assigned_purok_id)
                ->first();
        }

        return null;
    }

    /**
     * Resolve a resident for the assigned purok by server ID or mobile UUID.
     */
    private function resolveResidentForUser(User $user, ?int $id, ?string $mobileUuid): ?Resident
    {
        $query = Resident::query()->whereHas('household', function ($builder) use ($user): void {
            $builder->where('purok_id', $user->assigned_purok_id);
        });

        if ($id) {
            $resident = (clone $query)->whereKey($id)->first();

            if ($resident) {
                return $resident;
            }
        }

        if ($mobileUuid) {
            return (clone $query)->where('mobile_uuid', $mobileUuid)->first();
        }

        return null;
    }

    /**
     * Resolve a field visit for the assigned purok by server ID or mobile UUID.
     */
    private function resolveFieldVisitForUser(User $user, ?int $id, ?string $mobileUuid): ?FieldVisit
    {
        $query = FieldVisit::query()->whereHas('household', function ($builder) use ($user): void {
            $builder->where('purok_id', $user->assigned_purok_id);
        });

        if ($id !== null) {
            $visit = (clone $query)->whereKey($id)->first();

            return $visit && (! $mobileUuid || $visit->mobile_uuid === $mobileUuid) ? $visit : null;
        }

        if ($mobileUuid) {
            return (clone $query)->where('mobile_uuid', $mobileUuid)->first();
        }

        return null;
    }

    /**
     * Resolve a resident anywhere inside the assigned barangay.
     */
    private function resolveResidentForBarangay(User $user, ?int $id): ?Resident
    {
        if (! $id) {
            return null;
        }

        return Resident::query()
            ->whereKey($id)
            ->whereHas('household.purok', function ($builder) use ($user): void {
                $builder->where('barangay_id', $user->assigned_barangay_id);
            })
            ->with(['household.purok.barangay', 'socioEconomicProfile'])
            ->first();
    }

    /**
     * Resolve a PhilPEN assessment by server ID or mobile UUID within the assigned barangay.
     */
    private function resolveRiskAssessmentForUser(User $user, ?int $id, ?string $mobileUuid): ?PhilpenRiskAssessment
    {
        $query = PhilpenRiskAssessment::query()->where('barangay_id', $user->assigned_barangay_id);

        if ($id) {
            $assessment = (clone $query)->whereKey($id)->first();

            if ($assessment) {
                return $assessment;
            }
        }

        if ($mobileUuid) {
            return (clone $query)->where('mobile_uuid', $mobileUuid)->first();
        }

        return null;
    }

    private function riskAssessmentMatchesExisting(
        PhilpenRiskAssessment $assessment,
        array $attributes
    ): bool {
        foreach ($attributes as $field => $expectedValue) {
            if (! $this->riskAssessmentValuesMatch(
                $assessment->getAttribute($field),
                $expectedValue
            )) {
                return false;
            }
        }

        return true;
    }

    private function riskAssessmentValuesMatch(mixed $currentValue, mixed $expectedValue): bool
    {
        return $this->normalizeRiskAssessmentValue($currentValue)
            === $this->normalizeRiskAssessmentValue($expectedValue);
    }

    private function normalizeRiskAssessmentValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_array($value)) {
            ksort($value);

            return array_map(
                fn (mixed $item): mixed => $this->normalizeRiskAssessmentValue($item),
                $value
            );
        }

        if (is_bool($value) || $value === null) {
            return $value;
        }

        if (is_numeric($value)) {
            return round((float) $value, 4);
        }

        return $value;
    }

    private function calculateBodyMassIndex(mixed $weightKg, mixed $heightCm): ?float
    {
        $weight = is_numeric($weightKg) ? (float) $weightKg : null;
        $height = is_numeric($heightCm) ? (float) $heightCm : null;

        if (! $weight || ! $height || $height <= 0) {
            return null;
        }

        $heightMeters = $height / 100;

        if ($heightMeters <= 0) {
            return null;
        }

        return round($weight / ($heightMeters * $heightMeters), 2);
    }

    private function requiresImmediateReferral(array $redFlags): bool
    {
        foreach ([
            'chest_pain',
            'difficulty_breathing',
            'slurred_speech',
            'facial_asymmetry',
        ] as $criticalKey) {
            if (($redFlags[$criticalKey] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    private function identitySnapshotFor(Resident $resident, User $user, string $assessmentDate, array $validated): array
    {
        return [
            'barangay' => $resident->household?->purok?->barangay?->name,
            'purok' => $resident->household?->purok?->display_name,
            'bhw_name' => $user->name,
            'assessment_date' => $assessmentDate,
            'resident_name' => $resident->formal_name,
            'first_name' => $resident->first_name,
            'middle_name' => $resident->middle_name,
            'last_name' => $resident->last_name,
            'age_years' => $resident->birth_date
                ? (int) $resident->birth_date->diffInYears($assessmentDate)
                : null,
            'birth_date' => optional($resident->birth_date)->toDateString(),
            'sex' => $resident->sex,
            'contact_number' => $validated['contact_number'] ?? $resident->contact_number,
            'civil_status' => $validated['civil_status'] ?? $resident->civil_status,
            'religion' => $validated['religion'] ?? $resident->religion,
            'ethnicity' => $validated['ethnicity'] ?? $resident->socioEconomicProfile?->ethnicity,
        ];
    }

    /**
     * Persist visit photos and return their stored metadata.
     */
    private function storeVisitPhotos(array $photos): array
    {
        $storedPhotos = [];

        try {
            foreach ($photos as $photo) {
                $binary = $this->decodePhotoPayload($photo['data']);
                $mimeType = strtolower((string) ($photo['mime_type'] ?? 'image/jpeg'));
                $extension = $this->extensionForMimeType($mimeType);
                $path = 'visit-photos/'.now()->format('Y/m').'/'.Str::uuid().'.'.$extension;

                if (Storage::disk('local')->put($path, $binary) !== true) {
                    throw new \RuntimeException('Visit photo storage failed.');
                }

                $storedPhotos[] = [
                    'path' => $path,
                    'file_name' => $photo['file_name'] ?? basename($path),
                    'mime_type' => $mimeType,
                    'file_size_bytes' => strlen($binary),
                    'captured_at' => $photo['captured_at'] ?? null,
                ];
            }
        } catch (\Throwable $exception) {
            // The caller only owns cleanup after the complete photo operation returns.
            $this->deleteVisitPhotos($storedPhotos);

            throw $exception;
        }

        return $storedPhotos;
    }

    /**
     * Delete stored visit photos.
     */
    private function deleteVisitPhotos(array $photos): void
    {
        foreach ($photos as $photo) {
            $path = $photo['path'] ?? null;

            if ($path) {
                Storage::disk('local')->delete($path);
            }
        }
    }

    /**
     * Decode a base64 or data-URI photo payload.
     */
    private function decodePhotoPayload(string $payload): string
    {
        $data = str_contains($payload, ',')
            ? substr($payload, (int) strpos($payload, ',') + 1)
            : $payload;

        $decoded = base64_decode($data, true);

        if ($decoded === false) {
            throw new \RuntimeException('The photo payload is not valid base64 data.');
        }

        return $decoded;
    }

    /**
     * Convert common image MIME types to file extensions.
     */
    private function extensionForMimeType(string $mimeType): string
    {
        return match ($mimeType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/heic', 'image/heif' => 'heic',
            default => 'jpg',
        };
    }

    /**
     * Determine sync status from successes and failures.
     */
    private function determineStatus(int $recordsSynced, int $totalRecords, int $failureCount): string
    {
        if ($failureCount === 0) {
            return SyncLog::STATUS_SUCCESS;
        }

        if ($recordsSynced === 0 && $totalRecords > 0) {
            return SyncLog::STATUS_FAILED;
        }

        return SyncLog::STATUS_PARTIAL;
    }

    /**
     * Create a normalized failure payload.
     */
    private function failure(string $collection, int $index, string $message): array
    {
        return [
            'success' => false,
            'failure' => [
                'collection' => $collection,
                'index' => $index,
                'message' => $message,
            ],
        ];
    }

    /**
     * Build a human-readable error summary for logs and responses.
     */
    private function errorMessageFor(string $status, array $failures): ?string
    {
        if ($status === SyncLog::STATUS_SUCCESS) {
            return null;
        }

        $firstFailure = $failures[0]['message'] ?? 'Unknown sync failure.';

        if ($status === SyncLog::STATUS_FAILED) {
            return 'Sync failed. '.$firstFailure;
        }

        return 'Sync completed with some rejected records. '.$firstFailure;
    }
}
