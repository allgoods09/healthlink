<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        // Get the model class name from the original_table
        $modelClass = $this->original_model;
        
        if (!$modelClass) {
            throw new \Exception("Cannot restore: Unknown model type {$this->original_table}");
        }
        
        // Create a new instance of the model
        $model = new $modelClass();
        
        // Fill with the archived data
        $model->fill($this->data_snapshot);
        
        // Save the restored record
        $model->save();
        
        // Delete the archive entry
        $this->forceDelete();
        
        return $model;
    }
}