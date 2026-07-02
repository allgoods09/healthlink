<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'event_type',
        'event_description',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The event types available.
     */
    public const EVENT_TYPES = [
        'login' => 'User Logged In',
        'logout' => 'User Logged Out',
        'failed_login' => 'Failed Login Attempt',
        'password_reset' => 'Password Reset',
        'created' => 'Record Created',
        'updated' => 'Record Updated',
        'deleted' => 'Record Deleted',
        'restored' => 'Record Restored',
        'force_deleted' => 'Record Permanently Deleted',
        'synced' => 'Data Synced',
        'backup_generated' => 'Backup Generated',
        'backup_restored' => 'Backup Restored',
        'token_revoked' => 'API Token Revoked',
        'status_toggled' => 'Status Toggled',
        'exported' => 'Data Exported',
    ];

    // =============================================
    // RELATIONSHIPS
    // =============================================

    /**
     * Get the user who performed the action.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // =============================================
    // SCOPES
    // =============================================

    /**
     * Scope a query to filter by event type.
     */
    public function scopeEventType($query, $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope a query to filter by user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to filter by model.
     */
    public function scopeForModel($query, $modelType, $modelId)
    {
        return $query->where('model_type', $modelType)
                     ->where('model_id', $modelId);
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
     * Get the event type label.
     */
    public function getEventTypeLabelAttribute(): string
    {
        return self::EVENT_TYPES[$this->event_type] ?? $this->event_type;
    }

    /**
     * Get the user's name or 'System' for null users.
     */
    public function getActorNameAttribute(): string
    {
        return $this->user ? $this->user->name : 'System';
    }

    /**
     * Get a summary of changes made.
     */
    public function getChangesSummaryAttribute(): string
    {
        if ($this->old_values && $this->new_values) {
            return 'Updated record';
        }
        
        if ($this->event_type === 'created') {
            return 'Created new record';
        }
        
        if ($this->event_type === 'deleted') {
            return 'Deleted record';
        }
        
        return $this->event_description;
    }

    // =============================================
    // HELPER METHODS
    // =============================================

    /**
     * Log an action.
     */
    public static function log(array $data)
    {
        return static::create($data);
    }

    /**
     * Log a failed login attempt.
     */
    public static function logFailedLogin($email, $ip, $userAgent)
    {
        return static::create([
            'event_type' => 'failed_login',
            'event_description' => "Failed login attempt for email: {$email}",
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'metadata' => ['email' => $email],
        ]);
    }

    /**
     * Log a data mutation.
     */
    public static function logMutation($eventType, $user, $model, $oldValues = null, $newValues = null)
    {
        return static::create([
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'event_description' => class_basename($model) . ' ' . $eventType,
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}