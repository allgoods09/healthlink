<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'device_name',
        'device_model',
        'app_version',
        'records_synced',
        'payload_size',
        'sync_duration',
        'status',
        'error_message',
        'ip_address',
        'network_type',
        'sync_metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'records_synced' => 'integer',
        'payload_size' => 'integer',
        'sync_duration' => 'integer',
        'sync_metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The sync status types.
     */
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PARTIAL = 'partial';

    // =============================================
    // RELATIONSHIPS
    // =============================================

    /**
     * Get the user who performed the sync.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // =============================================
    // SCOPES
    // =============================================

    /**
     * Scope a query to only include successful syncs.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /**
     * Scope a query to only include failed syncs.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope a query to only include partial syncs.
     */
    public function scopePartial($query)
    {
        return $query->where('status', self::STATUS_PARTIAL);
    }

    /**
     * Scope a query to filter by user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
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
     * Get the status label with color.
     */
    public function getStatusBadgeAttribute(): string
    {
        $badges = [
            'success' => 'success',
            'failed' => 'danger',
            'partial' => 'warning',
        ];
        
        $color = $badges[$this->status] ?? 'secondary';
        return "<span class='badge badge-{$color}'>{$this->status}</span>";
    }

    /**
     * Get the formatted payload size.
     */
    public function getFormattedPayloadSizeAttribute(): string
    {
        if (!$this->payload_size) {
            return 'N/A';
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->payload_size;
        $unitIndex = 0;
        
        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }
        
        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Get the formatted sync duration.
     */
    public function getFormattedDurationAttribute(): string
    {
        if (!$this->sync_duration) {
            return 'N/A';
        }
        
        if ($this->sync_duration < 1000) {
            return $this->sync_duration . ' ms';
        }
        
        return round($this->sync_duration / 1000, 2) . ' s';
    }
}