<?php

namespace App\Models;

use App\Support\Nutrition\OptCycleRules;
use Illuminate\Database\Eloquent\Model;

class OptCycleEntry extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'birth_date' => 'date',
        'ip_membership' => 'boolean',
        'caregiver_confirmed_at' => 'datetime',
        'ip_confirmed_at' => 'datetime',
    ];

    public function cycle()
    {
        return $this->belongsTo(OptCycle::class, 'opt_cycle_id');
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }

    public function measurement()
    {
        return $this->hasOne(OptMeasurement::class);
    }

    public function getChildNameAttribute(): string
    {
        return trim($this->last_name.', '.implode(' ', array_filter([$this->first_name, $this->middle_name, $this->suffix])));
    }

    public function getReferenceAgeAttribute(): int
    {
        return OptCycleRules::ageMonths($this->birth_date, $this->cycle->reference_date);
    }

    public function getMeasurementStatusAttribute(): string
    {
        return $this->measurement ? 'Measured' : 'Unmeasured';
    }

    public function getReadinessIssuesAttribute(): array
    {
        $issues = [];
        if (! $this->caregiver_name || ! $this->caregiver_confirmed_at) {
            $issues[] = 'Caregiver not confirmed';
        }
        if ($this->ip_membership === null || ! $this->ip_confirmed_at) {
            $issues[] = 'IP membership not confirmed';
        }
        if (! $this->purok_name || ! $this->address) {
            $issues[] = 'Location incomplete';
        }
        if (! in_array($this->sex, ['Male', 'Female'], true)) {
            $issues[] = 'Sex incomplete';
        }

        return $issues;
    }

    public function scopeDataReady($query)
    {
        return $query->whereNotNull('caregiver_name')->where('caregiver_name', '!=', '')
            ->whereNotNull('caregiver_confirmed_at')->whereNotNull('ip_membership')->whereNotNull('ip_confirmed_at')
            ->whereNotNull('purok_name')->where('purok_name', '!=', '')
            ->whereNotNull('address')->where('address', '!=', '')->whereIn('sex', ['Male', 'Female']);
    }
}
