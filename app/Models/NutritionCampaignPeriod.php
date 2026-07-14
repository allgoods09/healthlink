<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NutritionCampaignPeriod extends Model
{
    use HasFactory;

    public const TYPE_OPT_PLUS = 'opt_plus';
    public const TYPE_FEEDING_PROGRAM = 'feeding_program';
    public const TYPE_MATERNAL_SURVEILLANCE = 'maternal_surveillance';
    public const TYPE_MICRONUTRIENT = 'micronutrient';

    public const TYPES = [
        self::TYPE_OPT_PLUS => 'OPT+ Campaign',
        self::TYPE_FEEDING_PROGRAM => 'Feeding Program Cycle',
        self::TYPE_MATERNAL_SURVEILLANCE => 'Maternal Surveillance',
        self::TYPE_MICRONUTRIENT => 'Micronutrient Distribution',
    ];

    protected $fillable = [
        'barangay_id',
        'created_by_user_id',
        'name',
        'campaign_type',
        'starts_on',
        'ends_on',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_active' => 'boolean',
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

    public function optMeasurements()
    {
        return $this->hasMany(OptMeasurement::class, 'campaign_period_id');
    }

    public function feedingPrograms()
    {
        return $this->hasMany(FeedingProgram::class, 'campaign_period_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getCampaignTypeLabelAttribute(): string
    {
        return self::TYPES[$this->campaign_type] ?? str((string) $this->campaign_type)->replace('_', ' ')->title()->toString();
    }
}
