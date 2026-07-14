<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaternalNutritionHistory extends Model
{
    use HasFactory;

    public const EVENT_PREGNANCY_STATUS_CHANGE = 'pregnancy_status_change';
    public const EVENT_LACTATING_STATUS_CHANGE = 'lactating_status_change';
    public const EVENT_PRENATAL_WEIGHT_CHECK = 'prenatal_weight_check';
    public const EVENT_DELIVERY_POSTPARTUM_UPDATE = 'delivery_postpartum_update';

    public const EVENT_TYPES = [
        self::EVENT_PREGNANCY_STATUS_CHANGE => 'Pregnancy Status Change',
        self::EVENT_LACTATING_STATUS_CHANGE => 'Lactating Status Change',
        self::EVENT_PRENATAL_WEIGHT_CHECK => 'Prenatal Weight Check',
        self::EVENT_DELIVERY_POSTPARTUM_UPDATE => 'Delivery / Postpartum Update',
    ];

    protected $fillable = [
        'resident_id',
        'recorded_by_user_id',
        'event_type',
        'event_date',
        'gestational_age_weeks',
        'weight_kg',
        'notes',
    ];

    protected $casts = [
        'event_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function getEventTypeLabelAttribute(): string
    {
        return self::EVENT_TYPES[$this->event_type] ?? str((string) $this->event_type)->replace('_', ' ')->title()->toString();
    }
}
