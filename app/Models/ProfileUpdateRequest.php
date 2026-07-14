<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileUpdateRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const SUBJECT_RESIDENT = 'resident';
    public const SUBJECT_HOUSEHOLD = 'household';

    protected $fillable = [
        'submitted_by_user_id',
        'barangay_id',
        'subject_type',
        'subject_id',
        'current_snapshot',
        'proposed_changes',
        'request_reason',
        'request_status',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_notes',
        'applied_at',
    ];

    protected $casts = [
        'current_snapshot' => 'array',
        'proposed_changes' => 'array',
        'reviewed_at' => 'datetime',
        'applied_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class, 'subject_id');
    }

    public function household()
    {
        return $this->belongsTo(Household::class, 'subject_id');
    }

    public function scopePending($query)
    {
        return $query->where('request_status', self::STATUS_PENDING);
    }

    public function getSubjectLabelAttribute(): string
    {
        return match ($this->subject_type) {
            self::SUBJECT_RESIDENT => 'Resident',
            self::SUBJECT_HOUSEHOLD => 'Household',
            default => str((string) $this->subject_type)->title()->toString(),
        };
    }

    public function getRequestStatusLabelAttribute(): string
    {
        return match ($this->request_status) {
            self::STATUS_PENDING => 'Pending Review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            default => 'Unknown',
        };
    }

    public function getSubjectNameAttribute(): string
    {
        return match ($this->subject_type) {
            self::SUBJECT_RESIDENT => $this->resident?->formal_name ?? 'Resident #'.$this->subject_id,
            self::SUBJECT_HOUSEHOLD => $this->household?->full_identifier ?? 'Household #'.$this->subject_id,
            default => $this->subject_label.' #'.$this->subject_id,
        };
    }
}
