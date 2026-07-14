<?php

namespace App\Support;

use App\Models\FieldVisit;
use App\Models\Household;
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
    /**
     * Process a mobile sync payload and return a structured result.
     */
    public function process(User $user, array $payload): array
    {
        $households = array_values($payload['households'] ?? []);
        $residents = array_values($payload['residents'] ?? []);
        $fieldVisits = array_values($payload['field_visits'] ?? []);
        $recordsSynced = 0;
        $failures = [];
        $resolvedRecords = [
            'households' => [],
            'residents' => [],
            'field_visits' => [],
        ];
        $summary = [
            'households_received' => count($households),
            'residents_received' => count($residents),
            'field_visits_received' => count($fieldVisits),
            'households_synced' => 0,
            'residents_synced' => 0,
            'field_visits_synced' => 0,
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

        $summary['failure_count'] = count($failures);
        $summary['failed_records'] = array_slice($failures, 0, 10);
        $totalRecords = count($households) + count($residents) + count($fieldVisits);
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

    /**
     * Sync a single household payload.
     */
    private function syncHousehold(User $user, mixed $record, int $index): array
    {
        try {
            $validated = $this->validateHouseholdRecord($record);
        } catch (ValidationException $exception) {
            return $this->failure('households', $index, $exception->validator->errors()->first());
        }

        $household = $this->resolveHouseholdForUser(
            $user,
            $validated['id'] ?? null,
            $validated['mobile_uuid'] ?? null
        );

        $creating = ! $household;

        if (! $household && empty($validated['mobile_uuid'])) {
            return $this->failure('households', $index, 'New households require a mobile UUID.');
        }

        if (! $household) {
            $household = new Household([
                'mobile_uuid' => $validated['mobile_uuid'],
                'purok_id' => $user->assigned_purok_id,
            ]);
        }

        $attributes = [];

        foreach (['mobile_uuid', 'household_no', 'household_address', 'is_social_aid_beneficiary', 'is_active'] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = $validated[$field];
            }
        }

        if ($creating) {
            foreach (['household_no', 'household_address', 'is_social_aid_beneficiary', 'is_active'] as $field) {
                if (! array_key_exists($field, $attributes)) {
                    return $this->failure('households', $index, "New households require the '{$field}' field.");
                }
            }
        }

        if (! $creating && $attributes === []) {
            return $this->failure('households', $index, 'No updatable household fields were provided.');
        }

        $candidateHouseholdNo = $attributes['household_no'] ?? $household->household_no;

        $duplicate = Household::query()
            ->where('purok_id', $user->assigned_purok_id)
            ->where('household_no', $candidateHouseholdNo)
            ->when($household->exists, fn ($query) => $query->whereKeyNot($household->id))
            ->exists();

        if ($duplicate) {
            return $this->failure('households', $index, 'This household number already exists in the assigned purok.');
        }

        try {
            DB::transaction(function () use ($household, $attributes, $user): void {
                $household->fill(array_merge($attributes, [
                    'purok_id' => $user->assigned_purok_id,
                ]));

                $household->save();
            });
        } catch (\Throwable $exception) {
            return $this->failure('households', $index, 'Household update failed: '.$exception->getMessage());
        }

        return [
            'success' => true,
            'record' => [
                'id' => $household->id,
                'mobile_uuid' => $household->mobile_uuid,
                'operation' => $creating ? 'created' : 'updated',
                'updated_at' => optional($household->updated_at)->toIso8601String(),
            ],
        ];
    }

    /**
     * Sync a single resident payload.
     */
    private function syncResident(User $user, mixed $record, int $index): array
    {
        try {
            $validated = $this->validateResidentRecord($record);
        } catch (ValidationException $exception) {
            return $this->failure('residents', $index, $exception->validator->errors()->first());
        }

        $household = $this->resolveHouseholdForUser(
            $user,
            $validated['household_id'] ?? null,
            $validated['household_mobile_uuid'] ?? null
        );

        if (! $household) {
            return $this->failure('residents', $index, 'Resident household not found in the assigned purok.');
        }

        $resident = $this->resolveResidentForUser(
            $user,
            $validated['id'] ?? null,
            $validated['mobile_uuid'] ?? null
        );

        $creating = ! $resident;

        if (! $resident && empty($validated['mobile_uuid'])) {
            return $this->failure('residents', $index, 'New residents require a mobile UUID.');
        }

        if (! $resident) {
            $resident = new Resident([
                'mobile_uuid' => $validated['mobile_uuid'],
            ]);
        }

        $attributes = [];

        foreach ([
            'mobile_uuid',
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
            'is_active',
        ] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = $validated[$field];
            }
        }

        if ($creating) {
            foreach ([
                'last_name',
                'first_name',
                'birth_date',
                'birth_place',
                'sex',
                'civil_status',
                'citizenship',
                'relationship_to_head',
                'is_active',
            ] as $field) {
                if (! array_key_exists($field, $attributes)) {
                    return $this->failure('residents', $index, "New residents require the '{$field}' field.");
                }
            }
        }

        if (! $creating && $attributes === [] && ! $household->is($resident->household)) {
            $attributes['household_id'] = $household->id;
        }

        if (! $creating && $attributes === []) {
            return $this->failure('residents', $index, 'No updatable resident fields were provided.');
        }

        $candidate = array_merge([
            'philsys_card_no' => $resident->philsys_card_no,
            'last_name' => $resident->last_name,
            'first_name' => $resident->first_name,
            'birth_date' => optional($resident->birth_date)->toDateString(),
        ], $attributes);

        if (! empty($candidate['philsys_card_no'])) {
            $duplicatePhilSys = Resident::query()
                ->where('philsys_card_no', $candidate['philsys_card_no'])
                ->when($resident->exists, fn ($query) => $query->whereKeyNot($resident->id))
                ->exists();

            if ($duplicatePhilSys) {
                return $this->failure('residents', $index, 'This PhilSys ID is already registered.');
            }
        }

        $duplicateIdentity = Resident::query()
            ->whereRaw('LOWER(first_name) = ?', [strtolower((string) $candidate['first_name'])])
            ->whereRaw('LOWER(last_name) = ?', [strtolower((string) $candidate['last_name'])])
            ->whereDate('birth_date', $candidate['birth_date'])
            ->whereHas('household', function ($query) use ($user): void {
                $query->where('purok_id', $user->assigned_purok_id);
            })
            ->when($resident->exists, fn ($query) => $query->whereKeyNot($resident->id))
            ->exists();

        if ($duplicateIdentity) {
            return $this->failure('residents', $index, 'A resident with the same name and birth date already exists in this purok.');
        }

        try {
            DB::transaction(function () use ($resident, $attributes, $household): void {
                $resident->fill(array_merge($attributes, [
                    'household_id' => $household->id,
                ]));

                $resident->save();
            });
        } catch (\Throwable $exception) {
            return $this->failure('residents', $index, 'Resident update failed: '.$exception->getMessage());
        }

        return [
            'success' => true,
            'record' => [
                'id' => $resident->id,
                'mobile_uuid' => $resident->mobile_uuid,
                'household_id' => $resident->household_id,
                'operation' => $creating ? 'created' : 'updated',
                'updated_at' => optional($resident->updated_at)->toIso8601String(),
            ],
        ];
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
            return $this->failure('field_visits', $index, 'Visit photo upload failed: '.$exception->getMessage());
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
            DB::transaction(function () use ($visit, $attributes, $household, $user, $retainedPhotos, $storedPhotos): void {
                $visit->fill(array_merge($attributes, [
                    'household_id' => $household->id,
                    'recorded_by_user_id' => $user->id,
                    'photos' => array_values([...$retainedPhotos, ...$storedPhotos]),
                    'source' => 'mobile',
                    'last_synced_at' => now(),
                ]));

                $visit->save();
            });
        } catch (\Throwable $exception) {
            $this->deleteVisitPhotos($storedPhotos);

            return $this->failure('field_visits', $index, 'Field visit update failed: '.$exception->getMessage());
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
     * Validate a mobile household record.
     */
    private function validateHouseholdRecord(mixed $record): array
    {
        return Validator::make(is_array($record) ? $record : [], [
            'id' => ['nullable', 'integer'],
            'mobile_uuid' => ['nullable', 'uuid', 'required_without:id'],
            'household_no' => ['sometimes', 'string', 'max:50'],
            'household_address' => ['sometimes', 'string'],
            'is_social_aid_beneficiary' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ])->validate();
    }

    /**
     * Validate a mobile resident record.
     */
    private function validateResidentRecord(mixed $record): array
    {
        return Validator::make(is_array($record) ? $record : [], [
            'id' => ['nullable', 'integer'],
            'mobile_uuid' => ['nullable', 'uuid', 'required_without:id'],
            'household_id' => ['nullable', 'integer'],
            'household_mobile_uuid' => ['nullable', 'uuid', 'required_without:household_id'],
            'philsys_card_no' => ['nullable', 'string', 'max:50'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['sometimes', 'date'],
            'birth_place' => ['sometimes', 'string', 'max:255'],
            'sex' => ['sometimes', 'in:Male,Female'],
            'civil_status' => ['sometimes', 'string', 'max:50'],
            'citizenship' => ['sometimes', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'email_address' => ['nullable', 'email', 'max:100'],
            'relationship_to_head' => ['sometimes', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
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
     * Resolve a household for the assigned purok by server ID or mobile UUID.
     */
    private function resolveHouseholdForUser(User $user, ?int $id, ?string $mobileUuid): ?Household
    {
        if ($id) {
            $household = Household::query()
                ->whereKey($id)
                ->where('purok_id', $user->assigned_purok_id)
                ->first();

            if ($household) {
                return $household;
            }
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

        if ($id) {
            $visit = (clone $query)->whereKey($id)->first();

            if ($visit) {
                return $visit;
            }
        }

        if ($mobileUuid) {
            return (clone $query)->where('mobile_uuid', $mobileUuid)->first();
        }

        return null;
    }

    /**
     * Persist visit photos and return their stored metadata.
     */
    private function storeVisitPhotos(array $photos): array
    {
        $storedPhotos = [];

        foreach ($photos as $photo) {
            $binary = $this->decodePhotoPayload($photo['data']);
            $mimeType = strtolower((string) ($photo['mime_type'] ?? 'image/jpeg'));
            $extension = $this->extensionForMimeType($mimeType);
            $path = 'visit-photos/'.now()->format('Y/m').'/'.Str::uuid().'.'.$extension;

            Storage::disk('local')->put($path, $binary);

            $storedPhotos[] = [
                'path' => $path,
                'file_name' => $photo['file_name'] ?? basename($path),
                'mime_type' => $mimeType,
                'file_size_bytes' => strlen($binary),
                'captured_at' => $photo['captured_at'] ?? null,
            ];
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
