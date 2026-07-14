<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunityCampaignAssignment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_SKIPPED = 'skipped';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_SKIPPED => 'Skipped',
    ];

    protected $fillable = [
        'community_campaign_id',
        'assigned_bhw_user_id',
        'resident_id',
        'household_id',
        'assignment_status',
        'completed_at',
        'field_notes',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(CommunityCampaign::class, 'community_campaign_id');
    }

    public function assignedBhw()
    {
        return $this->belongsTo(User::class, 'assigned_bhw_user_id');
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function getAssignmentStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->assignment_status] ?? str((string) $this->assignment_status)->replace('_', ' ')->title()->toString();
    }

    public function getTargetLabelAttribute(): string
    {
        if ($this->resident) {
            return $this->resident->formal_name;
        }

        if ($this->household) {
            return $this->household->full_identifier;
        }

        return 'Unassigned roster target';
    }
}
