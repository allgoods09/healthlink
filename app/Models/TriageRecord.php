<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TriageRecord extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'resident_id',
        'household_id',
        'barangay_id',
        'purok_id',
        'recorded_by_user_id',
        'consumed_by_user_id',
        'triage_status',
        'measured_at',
        'bp_systolic',
        'bp_diastolic',
        'heart_rate',
        'temperature_celsius',
        'respiratory_rate',
        'blood_glucose_mg_dl',
        'triage_notes',
        'consumed_at',
    ];

    protected $casts = [
        'measured_at' => 'datetime',
        'consumed_at' => 'datetime',
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

    public function consumedBy()
    {
        return $this->belongsTo(User::class, 'consumed_by_user_id');
    }

    public function clinicalEncounter()
    {
        return $this->hasOne(ClinicalEncounter::class);
    }

    public function scopePending($query)
    {
        return $query->where('triage_status', self::STATUS_PENDING);
    }

    public function getTriageStatusLabelAttribute(): string
    {
        return match ($this->triage_status) {
            self::STATUS_PENDING => 'Pending Clinical Review',
            self::STATUS_REVIEWED => 'Reviewed',
            self::STATUS_CLOSED => 'Closed',
            default => 'Unknown',
        };
    }
}
