<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunityCampaign extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ARCHIVED = 'archived';

    public const TYPE_GENERAL = 'general';
    public const TYPE_IMMUNIZATION = 'immunization';
    public const TYPE_DEWORMING = 'deworming';
    public const TYPE_MAINTENANCE = 'maintenance';
    public const TYPE_HOUSEHOLD_SURVEY = 'household_survey';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    public const TYPES = [
        self::TYPE_GENERAL => 'General Community Campaign',
        self::TYPE_IMMUNIZATION => 'Immunization Drive',
        self::TYPE_DEWORMING => 'Mass Deworming',
        self::TYPE_MAINTENANCE => 'Maintenance Medicine Distribution',
        self::TYPE_HOUSEHOLD_SURVEY => 'Household Survey',
    ];

    protected $fillable = [
        'barangay_id',
        'created_by_user_id',
        'assigned_purok_id',
        'title',
        'campaign_type',
        'scheduled_for',
        'description',
        'campaign_status',
    ];

    protected $casts = [
        'scheduled_for' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignedPurok()
    {
        return $this->belongsTo(Purok::class, 'assigned_purok_id');
    }

    public function assignments()
    {
        return $this->hasMany(CommunityCampaignAssignment::class);
    }

    public function getCampaignStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->campaign_status] ?? str((string) $this->campaign_status)->replace('_', ' ')->title()->toString();
    }

    public function getCampaignTypeLabelAttribute(): string
    {
        return self::TYPES[$this->campaign_type] ?? str((string) $this->campaign_type)->replace('_', ' ')->title()->toString();
    }
}
