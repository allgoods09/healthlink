<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
        $classes = [
            'pending' => 'bg-amber-100 text-amber-800',
            'in_progress' => 'bg-sky-100 text-sky-800',
            'completed' => 'bg-emerald-100 text-emerald-800',
            'failed' => 'bg-rose-100 text-rose-800',
        ];

        $class = $classes[$this->status] ?? 'bg-gray-100 text-gray-800';

        return "<span class='inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {$class}'>".strtoupper(str_replace('_', ' ', $this->status)).'</span>';
    }

    /**
     * Check if the backup is expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Resolve a metadata value safely.
     */
    public function metadataValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata ?? [], $key, $default);
    }

    /**
     * Get the absolute local filesystem path for the backup.
     */
    public function getAbsolutePathAttribute(): string
    {
        return storage_path('app/'.$this->file_path);
    }

    /**
     * Check whether the backup file currently exists.
     */
    public function getHasStoredFileAttribute(): bool
    {
        return is_file($this->absolute_path);
    }

    /**
     * Get the current integrity status from metadata.
     */
    public function getIntegrityStatusAttribute(): string
    {
        return (string) $this->metadataValue('integrity_status', 'unverified');
    }

    /**
     * Get the last integrity check timestamp.
     */
    public function getLastVerifiedAtAttribute(): ?\Illuminate\Support\Carbon
    {
        $value = $this->metadataValue('integrity_checked_at');

        return $value ? Carbon::parse($value) : null;
    }

    /**
     * Get the checksum stored for the backup.
     */
    public function getChecksumSha256Attribute(): ?string
    {
        return $this->metadataValue('checksum_sha256');
    }

    /**
     * Get the total successful restore count.
     */
    public function getRestoreCountAttribute(): int
    {
        return (int) $this->metadataValue('restore_count', 0);
    }

    /**
     * Get the timestamp of the last successful restore.
     */
    public function getLastRestoredAtAttribute(): ?\Illuminate\Support\Carbon
    {
        $value = $this->metadataValue('last_restored_at');

        return $value ? Carbon::parse($value) : null;
    }

    /**
     * Get the integrity badge HTML.
     */
    public function getIntegrityBadgeAttribute(): string
    {
        $classes = [
            'verified' => 'bg-emerald-100 text-emerald-800',
            'unverified' => 'bg-gray-100 text-gray-800',
            'missing' => 'bg-rose-100 text-rose-800',
            'empty' => 'bg-amber-100 text-amber-800',
            'mismatch' => 'bg-rose-100 text-rose-800',
        ];

        $class = $classes[$this->integrity_status] ?? 'bg-gray-100 text-gray-800';

        return "<span class='inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {$class}'>".strtoupper($this->integrity_status).'</span>';
    }

    /**
     * Check whether the backup is eligible for integrity verification.
     */
    public function getIsVerifiableAttribute(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check whether the backup is eligible for restore.
     */
    public function getIsRestorableAttribute(): bool
    {
        return $this->status === self::STATUS_COMPLETED && $this->has_stored_file;
    }
}
