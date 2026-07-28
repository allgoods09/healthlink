<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhilpenRiskAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'mobile_uuid',
        'resident_id',
        'household_id',
        'barangay_id',
        'purok_id',
        'recorded_by_user_id',
        'source',
        'assessment_date',
        'age_years',
        'religion',
        'contact_number',
        'philhealth_number',
        'civil_status',
        'ethnicity',
        'pwd_id_number',
        'weight_kg',
        'height_cm',
        'body_mass_index',
        'waist_circumference_cm',
        'systolic_bp',
        'diastolic_bp',
        'employment_status',
        'ip_classification',
        'requires_immediate_referral',
        'identity_snapshot',
        'red_flags',
        'past_medical_history',
        'family_history',
        'tobacco_use',
        'alcohol_consumption_status',
        'alcohol_binge_flag',
        'physical_activity_met',
        'high_risk_diet_weekly',
        'blood_sugar_notes',
        'fbs_result',
        'rbs_result',
        'dm_symptoms',
        'lipid_profile_date',
        'total_cholesterol',
        'hdl',
        'ldl',
        'vldl',
        'triglycerides',
        'urinalysis_protein',
        'urinalysis_ketones',
        'urinalysis_date',
        'chronic_respiratory_symptoms',
        'lifestyle_modification',
        'anti_hypertensive_medications',
        'oral_hypoglycemic_medications',
        'follow_up_date',
        'remarks',
        'last_synced_at',
    ];

    protected $casts = [
        'assessment_date' => 'date',
        'lipid_profile_date' => 'date',
        'urinalysis_date' => 'date',
        'follow_up_date' => 'date',
        'weight_kg' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'body_mass_index' => 'decimal:2',
        'waist_circumference_cm' => 'decimal:2',
        'requires_immediate_referral' => 'boolean',
        'identity_snapshot' => 'array',
        'red_flags' => 'array',
        'past_medical_history' => 'array',
        'family_history' => 'array',
        'alcohol_binge_flag' => 'boolean',
        'physical_activity_met' => 'boolean',
        'high_risk_diet_weekly' => 'boolean',
        'dm_symptoms' => 'array',
        'chronic_respiratory_symptoms' => 'array',
        'lifestyle_modification' => 'boolean',
        'last_synced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function purok()
    {
        return $this->belongsTo(Purok::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function getAssessmentDateLabelAttribute(): string
    {
        return $this->assessment_date?->format('F j, Y') ?? 'No date recorded';
    }
}
