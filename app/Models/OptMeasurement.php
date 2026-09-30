<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OptMeasurement extends Model
{
    use HasFactory;

    public const POSTURE_STANDING = 'standing';

    public const POSTURE_RECUMBENT = 'recumbent';

    protected $fillable = [
        'resident_id',
        'barangay_id',
        'campaign_period_id',
        'measured_by_user_id',
        'measurement_date',
        'age_in_months',
        'sex_snapshot',
        'weight_kg',
        'height_cm',
        'measurement_posture',
        'weight_for_age_z_score',
        'weight_for_age_status',
        'height_for_age_z_score',
        'height_for_age_status',
        'weight_for_length_height_z_score',
        'weight_for_length_height_status',
        'remarks',
        'opt_cycle_entry_id',
        'assessment_source',
        'assessment_version',
        'assessment_error',
    ];

    protected $casts = [
        'measurement_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function cycleEntry()
    {
        return $this->belongsTo(OptCycleEntry::class, 'opt_cycle_entry_id');
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function campaignPeriod()
    {
        return $this->belongsTo(NutritionCampaignPeriod::class, 'campaign_period_id');
    }

    public function measuredBy()
    {
        return $this->belongsTo(User::class, 'measured_by_user_id');
    }

    public function getMeasurementPostureLabelAttribute(): string
    {
        return $this->measurement_posture === self::POSTURE_RECUMBENT
            ? 'Recumbent Length'
            : 'Standing Height';
    }

    public function getIsTargetClientAttribute(): bool
    {
        return in_array($this->weight_for_age_status, ['Severely Underweight', 'Underweight'], true)
            || in_array($this->height_for_age_status, ['Severely Stunted', 'Stunted'], true)
            || in_array($this->weight_for_length_height_status, ['Severely Wasted', 'Wasted'], true);
    }

    public function getTargetClientReasonsAttribute(): array
    {
        $reasons = [];

        if (in_array($this->weight_for_age_status, ['Severely Underweight', 'Underweight'], true)) {
            $reasons[] = $this->weight_for_age_status;
        }

        if (in_array($this->height_for_age_status, ['Severely Stunted', 'Stunted'], true)) {
            $reasons[] = $this->height_for_age_status;
        }

        if (in_array($this->weight_for_length_height_status, ['Severely Wasted', 'Wasted'], true)) {
            $reasons[] = $this->weight_for_length_height_status;
        }

        return $reasons;
    }
}
