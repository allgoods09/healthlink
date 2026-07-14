<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedingProgram extends Model
{
    use HasFactory;

    public const STATUS_PLANNED = 'planned';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PLANNED => 'Planned',
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $fillable = [
        'barangay_id',
        'campaign_period_id',
        'created_by_user_id',
        'name',
        'starts_on',
        'ends_on',
        'program_status',
        'description',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function campaignPeriod()
    {
        return $this->belongsTo(NutritionCampaignPeriod::class, 'campaign_period_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function enrollments()
    {
        return $this->hasMany(FeedingProgramEnrollment::class);
    }

    public function activeEnrollments()
    {
        return $this->hasMany(FeedingProgramEnrollment::class)->where('is_active', true);
    }

    public function getProgramStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->program_status] ?? str((string) $this->program_status)->replace('_', ' ')->title()->toString();
    }
}
