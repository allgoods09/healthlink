<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'filename',
        'file_path',
        'file_size',
        'backup_type',
        'status',
        'generated_by',
        'storage_location',
        'notes',
        'metadata',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'file_size' => 'integer',
        'metadata' => 'json',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The backup types available.
     */
    public const TYPES = [
        'full' => 'Full Database',
        'schema_only' => 'Schema Only',
        'data_only' => 'Data Only',
    ];

    /**
     * The backup statuses.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    /**
     * The storage locations.
     */
    public const STORAGE_LOCAL = 'local';
    public const STORAGE_EXTERNAL = 'external';
    public const STORAGE_CLOUD = 'cloud';

    // =============================================
    // RELATIONSHIPS
    // =============================================

    /**
     * Get the user who generated this backup.
     */
    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    // =============================================
    // SCOPES
    // =============================================

    /**
     * Scope a query to only include completed backups.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope a query to only include pending backups.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include failed backups.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope a query to only include full backups.
     */
    public function scopeFull($query)
    {
        return $query->where('backup_type', 'full');
    }

    /**
     * Scope a query to filter by storage location.
     */
    public function scopeStorageLocation($query, $location)
    {
        return $query->where('storage_location', $location);
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
     * Get the backup type label.
     */
    public function getBackupTypeLabelAttribute(): string
    {
        return self::TYPES[$this->backup_type] ?? $this->backup_type;
    }

    /**
     * Get the formatted file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return 'N/A';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $this->file_size;
        $unitIndex = 0;
        
        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }
        
        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Get the status badge HTML.
     */
    public function getStatusBadgeAttribute(): string
    {
        $badges = [
            'pending' => 'warning',
            'in_progress' => 'info',
            'completed' => 'success',
            'failed' => 'danger',
        ];
        
        $color = $badges[$this->status] ?? 'secondary';
        return "<span class='badge badge-{$color}'>{$this->status}</span>";
    }

    /**
     * Check if the backup is expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}