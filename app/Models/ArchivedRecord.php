<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ArchivedRecord extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'original_table',
        'original_id',
        'data_snapshot',
        'archived_by',
        'archiving_reason',
        'is_purged',
        'purged_at',
        'purged_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data_snapshot' => 'json',
        'is_purged' => 'boolean',
        'purged_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // =============================================
    // RELATIONSHIPS
    // =============================================

    /**
     * Get the user who archived this record.
     */
    public function archivedBy()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /**
     * Get the user who purged this record.
     */
    public function purgedBy()
    {
        return $this->belongsTo(User::class, 'purged_by');
    }

    // =============================================
    // SCOPES
    // =============================================

    /**
     * Scope a query to only include archived records from a specific table.
     */
    public function scopeFromTable($query, $table)
    {
        return $query->where('original_table', $table);
    }

    /**
     * Scope a query to only include purged records.
     */
    public function scopePurged($query)
    {
        return $query->where('is_purged', true);
    }

    /**
     * Scope a query to only include non-purged records.
     */
    public function scopeNotPurged($query)
    {
        return $query->where('is_purged', false);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    // =============================================
    // ACCESSORS
    // =============================================

    /**
     * Get the original model class name.
     */
    public function getOriginalModelAttribute()
    {
        $models = [
            'users' => User::class,
            'barangays' => Barangay::class,
            'puroks' => Purok::class,
            'households' => Household::class,
            'residents' => Resident::class,
        ];
        
        return $models[$this->original_table] ?? null;
    }

    /**
     * Get the display name of the archived record.
     */
    public function getDisplayNameAttribute(): string
    {
        $data = $this->data_snapshot;
        
        if (isset($data['name'])) {
            return $data['name'];
        }
        
        if (isset($data['first_name']) && isset($data['last_name'])) {
            return $data['first_name'] . ' ' . $data['last_name'];
        }
        
        if (isset($data['household_no'])) {
            return 'Household #' . $data['household_no'];
        }
        
        return 'Archived Record #' . $this->id;
    }

    // =============================================
    // HELPER METHODS
    // =============================================

    /**
     * Archive a record.
     */
    public static function archive($model, User $archivedBy, string $reason = null)
    {
        return static::create([
            'original_table' => $model->getTable(),
            'original_id' => $model->id,
            'data_snapshot' => $model->toArray(),
            'archived_by' => $archivedBy->id,
            'archiving_reason' => $reason,
        ]);
    }

    /**
     * Purge an archived record (soft delete it permanently from archive).
     */
    public function purge(User $purgedBy)
    {
        $this->ensurePurgeable();

        $this->update([
            'is_purged' => true,
            'purged_at' => now(),
            'purged_by' => $purgedBy->id,
        ]);
    }

    /**
     * Restore the archived record to its original table.
     */
    public function restore()
    {
        return DB::transaction(function () {
            $modelClass = $this->original_model;

            if (! $modelClass) {
                throw new RuntimeException("Cannot restore: Unknown model type {$this->original_table}");
            }

            $snapshot = collect($this->data_snapshot)->except(['deleted_at'])->all();

            $this->restoreDependencies($snapshot);
            $this->guardRestoreConflicts($modelClass, $snapshot);

            $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
            $model = $usesSoftDeletes
                ? $this->restoreSoftDeletedModel($modelClass, $snapshot)
                : $this->restoreFreshModel($modelClass, $snapshot);

            if ($model instanceof Household) {
                $individuallyArchivedResidentIds = self::query()
                    ->notPurged()
                    ->where('original_table', 'residents')
                    ->pluck('original_id');

                Resident::withTrashed()
                    ->where('household_id', $this->original_id)
                    ->when($individuallyArchivedResidentIds->isNotEmpty(), function ($query) use ($individuallyArchivedResidentIds): void {
                        $query->whereNotIn('id', $individuallyArchivedResidentIds);
                    })
                    ->restore();
            }

            $this->forceDelete();

            return $model;
        });
    }

    /**
     * Ensure restoring this record will not overwrite an active record.
     */
    public function guardRestoreConflicts(string $modelClass, array $snapshot): void
    {
        $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
        $existing = $usesSoftDeletes
            ? $modelClass::withTrashed()->find($this->original_id)
            : $modelClass::find($this->original_id);

        if ($existing && (! $usesSoftDeletes || ! method_exists($existing, 'trashed') || ! $existing->trashed())) {
            throw new RuntimeException("Cannot restore {$this->original_table} #{$this->original_id} because an active record with the same ID already exists.");
        }

        match ($this->original_table) {
            'users' => $this->assertNoUserConflict($snapshot),
            'barangays' => $this->assertNoBarangayConflict($snapshot),
            'puroks' => $this->assertNoPurokConflict($snapshot),
            'households' => $this->assertNoHouseholdConflict($snapshot),
            'residents' => $this->assertNoResidentConflict($snapshot),
            default => null,
        };
    }

    /**
     * Ensure purging this record will not orphan dependent archives.
     */
    public function ensurePurgeable(): void
    {
        $dependentArchives = $this->dependentArchives();

        if ($dependentArchives->isNotEmpty()) {
            $labels = $dependentArchives
                ->map(fn (ArchivedRecord $record) => "{$record->original_table} #{$record->original_id}")
                ->implode(', ');

            throw new RuntimeException("This archive cannot be purged yet because dependent archived records still exist: {$labels}.");
        }
    }

    /**
     * Restore a soft-deleted model instance or recreate it when missing.
     */
    private function restoreSoftDeletedModel(string $modelClass, array $snapshot): Model
    {
        $model = $modelClass::withTrashed()->find($this->original_id);

        if ($model) {
            if (method_exists($model, 'trashed') && $model->trashed()) {
                $model->restore();
            }

            $model->forceFill($snapshot)->save();

            return $model;
        }

        return $this->restoreFreshModel($modelClass, $snapshot);
    }

    /**
     * Recreate a model instance directly from the archived snapshot.
     */
    private function restoreFreshModel(string $modelClass, array $snapshot): Model
    {
        $model = new $modelClass();
        $model->forceFill($snapshot)->save();

        return $model;
    }

    /**
     * Restore any required parent dependencies before restoring the current record.
     */
    private function restoreDependencies(array $snapshot): void
    {
        if ($this->original_table === 'residents') {
            $this->restoreParentRecord($snapshot['household_id'] ?? null, 'households', Household::class);
        }

        if ($this->original_table === 'households') {
            $this->restoreParentRecord($snapshot['purok_id'] ?? null, 'puroks', Purok::class);
        }

        if ($this->original_table === 'puroks') {
            $this->restoreParentRecord($snapshot['barangay_id'] ?? null, 'barangays', Barangay::class);
        }

        if ($this->original_table === 'users') {
            $this->restoreParentRecord($snapshot['assigned_barangay_id'] ?? null, 'barangays', Barangay::class);
            $this->restoreParentRecord($snapshot['assigned_purok_id'] ?? null, 'puroks', Purok::class);
            $this->restoreParentRecord($snapshot['requested_barangay_id'] ?? null, 'barangays', Barangay::class);
            $this->restoreParentRecord($snapshot['requested_purok_id'] ?? null, 'puroks', Purok::class);
        }
    }

    /**
     * Restore a specific parent model if it is soft-deleted or still archived.
     */
    private function restoreParentRecord(?int $id, string $table, string $modelClass): void
    {
        if (! $id) {
            return;
        }

        $archivedParent = self::query()
            ->notPurged()
            ->where('original_table', $table)
            ->where('original_id', $id)
            ->first();

        if ($archivedParent) {
            $archivedParent->restore();

            return;
        }

        $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
        $existing = $usesSoftDeletes
            ? $modelClass::withTrashed()->find($id)
            : $modelClass::find($id);

        if ($existing) {
            if ($usesSoftDeletes && method_exists($existing, 'trashed') && $existing->trashed()) {
                $existing->restore();
            }

            return;
        }

        throw new RuntimeException("Cannot restore {$this->original_table} #{$this->original_id} because {$table} #{$id} is missing or has already been purged.");
    }

    /**
     * Get dependent archived records that would be orphaned by purging this record.
     */
    private function dependentArchives()
    {
        $archives = self::query()->notPurged()->where('id', '!=', $this->id)->get();

        return $archives->filter(function (ArchivedRecord $archive): bool {
            $snapshot = $archive->data_snapshot ?? [];

            return match ($this->original_table) {
                'barangays' => ($archive->original_table === 'puroks' && (int) ($snapshot['barangay_id'] ?? 0) === $this->original_id)
                    || ($archive->original_table === 'users' && in_array($this->original_id, [
                        (int) ($snapshot['assigned_barangay_id'] ?? 0),
                        (int) ($snapshot['requested_barangay_id'] ?? 0),
                    ], true)),
                'puroks' => ($archive->original_table === 'households' && (int) ($snapshot['purok_id'] ?? 0) === $this->original_id)
                    || ($archive->original_table === 'users' && in_array($this->original_id, [
                        (int) ($snapshot['assigned_purok_id'] ?? 0),
                        (int) ($snapshot['requested_purok_id'] ?? 0),
                    ], true)),
                'households' => $archive->original_table === 'residents' && (int) ($snapshot['household_id'] ?? 0) === $this->original_id,
                default => false,
            };
        })->values();
    }

    /**
     * Assert that restoring a user will not collide with another active email.
     */
    private function assertNoUserConflict(array $snapshot): void
    {
        if (! empty($snapshot['email']) && User::query()
            ->where('id', '!=', $this->original_id)
            ->where('email', $snapshot['email'])
            ->exists()) {
            throw new RuntimeException("Cannot restore user #{$this->original_id} because email {$snapshot['email']} is already in use.");
        }
    }

    /**
     * Assert that restoring a barangay will not collide with another active identity.
     */
    private function assertNoBarangayConflict(array $snapshot): void
    {
        if (! empty($snapshot['psgc_code']) && Barangay::query()
            ->where('id', '!=', $this->original_id)
            ->where('psgc_code', $snapshot['psgc_code'])
            ->exists()) {
            throw new RuntimeException("Cannot restore barangay #{$this->original_id} because PSGC code {$snapshot['psgc_code']} is already assigned to another record.");
        }

        if (! empty($snapshot['name']) && ! empty($snapshot['municipality']) && Barangay::query()
            ->where('id', '!=', $this->original_id)
            ->where('name', $snapshot['name'])
            ->where('municipality', $snapshot['municipality'])
            ->exists()) {
            throw new RuntimeException("Cannot restore barangay #{$this->original_id} because {$snapshot['name']} already exists in {$snapshot['municipality']}.");
        }
    }

    /**
     * Assert that restoring a purok will not collide with another active identity.
     */
    private function assertNoPurokConflict(array $snapshot): void
    {
        if (! empty($snapshot['barangay_id']) && isset($snapshot['purok_number']) && Purok::query()
            ->where('id', '!=', $this->original_id)
            ->where('barangay_id', $snapshot['barangay_id'])
            ->where('purok_number', $snapshot['purok_number'])
            ->exists()) {
            throw new RuntimeException("Cannot restore purok #{$this->original_id} because Purok {$snapshot['purok_number']} already exists in the target barangay.");
        }
    }

    /**
     * Assert that restoring a household will not collide with another active identity.
     */
    private function assertNoHouseholdConflict(array $snapshot): void
    {
        if (! empty($snapshot['purok_id']) && ! empty($snapshot['household_no']) && Household::query()
            ->where('id', '!=', $this->original_id)
            ->where('purok_id', $snapshot['purok_id'])
            ->where('household_no', $snapshot['household_no'])
            ->exists()) {
            throw new RuntimeException("Cannot restore household #{$this->original_id} because household number {$snapshot['household_no']} already exists in the target purok.");
        }
    }

    /**
     * Assert that restoring a resident will not collide with another active identity.
     */
    private function assertNoResidentConflict(array $snapshot): void
    {
        if (! empty($snapshot['philsys_card_no']) && Resident::query()
            ->where('id', '!=', $this->original_id)
            ->where('philsys_card_no', $snapshot['philsys_card_no'])
            ->exists()) {
            throw new RuntimeException("Cannot restore resident #{$this->original_id} because PhilSys ID {$snapshot['philsys_card_no']} is already assigned to another record.");
        }

        if (! empty($snapshot['household_id']) && ! empty($snapshot['first_name']) && ! empty($snapshot['last_name']) && ! empty($snapshot['birth_date']) && Resident::query()
            ->where('id', '!=', $this->original_id)
            ->where('household_id', $snapshot['household_id'])
            ->where('first_name', $snapshot['first_name'])
            ->where('last_name', $snapshot['last_name'])
            ->where('birth_date', $snapshot['birth_date'])
            ->exists()) {
            throw new RuntimeException("Cannot restore resident #{$this->original_id} because another resident with the same identity already exists in the target household.");
        }
    }
}
