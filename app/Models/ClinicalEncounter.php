<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClinicalEncounter extends Model
{
    use HasFactory;

    public const SOURCE_TRIAGE = 'triage';
    public const SOURCE_WALK_IN = 'walk_in';

    public const FOLLOW_UP_DUE = 'due';
    public const FOLLOW_UP_COMPLETED = 'completed';
    public const FOLLOW_UP_MISSED = 'missed';
    public const FOLLOW_UP_RESCHEDULED = 'rescheduled';

    protected $fillable = [
        'triage_record_id',
        'resident_id',
        'household_id',
        'barangay_id',
        'purok_id',
        'attended_by_user_id',
        'encounter_source',
        'encountered_at',
        'consultation_notes',
        'working_impression',
        'action_taken',
        'disposition',
        'follow_up_date',
        'follow_up_status',
        'follow_up_notes',
        'medicines_administered',
        'lifestyle_advice',
        'referral_notes',
        'return_instructions',
        'is_escalated_to_mho',
        'escalation_notes',
        'escalated_at',
        'closed_at',
    ];

    protected $casts = [
        'encountered_at' => 'datetime',
        'follow_up_date' => 'date',
        'is_escalated_to_mho' => 'boolean',
        'escalated_at' => 'datetime',
        'closed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(function (ClinicalEncounter $encounter): void {
            if (! $encounter->triage_record_id) {
                return;
            }

            $triageRecord = $encounter->triageRecord()->first();

            if (! $triageRecord) {
                return;
            }

            $triageRecord->forceFill([
                'consumed_by_user_id' => $encounter->attended_by_user_id,
                'consumed_at' => $triageRecord->consumed_at ?? now(),
                'triage_status' => $encounter->resolveLinkedTriageStatus(),
            ])->saveQuietly();
        });
    }

    public function triageRecord()
    {
        return $this->belongsTo(TriageRecord::class);
    }

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

    public function attendedBy()
    {
        return $this->belongsTo(User::class, 'attended_by_user_id');
    }

    public function mhoReview()
    {
        return $this->hasOne(MhoClinicalReview::class);
    }

    public function scopeDueFollowUp($query)
    {
        return $query
            ->whereNotNull('follow_up_date')
            ->whereIn('follow_up_status', [self::FOLLOW_UP_DUE, self::FOLLOW_UP_RESCHEDULED])
            ->whereDate('follow_up_date', '<=', now()->toDateString());
    }

    public function scopeActiveEscalations($query)
    {
        return $query
            ->where('is_escalated_to_mho', true)
            ->whereDoesntHave('mhoReview')
            ->whereNull('closed_at');
    }

    public function scopeReviewedByMho($query)
    {
        return $query->whereHas('mhoReview');
    }

    public function getEncounterSourceLabelAttribute(): string
    {
        return match ($this->encounter_source) {
            self::SOURCE_TRIAGE => 'From BHW Triage',
            self::SOURCE_WALK_IN => 'Direct Walk-In',
            default => 'Unknown',
        };
    }

    public function getFollowUpStatusLabelAttribute(): string
    {
        return match ($this->follow_up_status) {
            self::FOLLOW_UP_DUE => 'Due',
            self::FOLLOW_UP_COMPLETED => 'Completed',
            self::FOLLOW_UP_MISSED => 'Missed',
            self::FOLLOW_UP_RESCHEDULED => 'Rescheduled',
            default => 'No Follow-Up',
        };
    }

    public function getClinicalStatusLabelAttribute(): string
    {
        if ($this->is_escalated_to_mho && ! $this->mhoReview && is_null($this->closed_at)) {
            return 'Escalated to MHO';
        }

        if ($this->mhoReview && is_null($this->closed_at)) {
            return 'Reviewed by MHO';
        }

        if (in_array($this->follow_up_status, [self::FOLLOW_UP_DUE, self::FOLLOW_UP_RESCHEDULED], true)) {
            return 'Follow-Up Pending';
        }

        if ($this->follow_up_status === self::FOLLOW_UP_COMPLETED) {
            return 'Follow-Up Completed';
        }

        if ($this->follow_up_status === self::FOLLOW_UP_MISSED) {
            return 'Follow-Up Missed';
        }

        return $this->closed_at ? 'Closed' : 'Active';
    }

    public function resolveLinkedTriageStatus(): string
    {
        if ($this->closed_at && ! $this->is_escalated_to_mho) {
            return TriageRecord::STATUS_CLOSED;
        }

        return TriageRecord::STATUS_REVIEWED;
    }
}
