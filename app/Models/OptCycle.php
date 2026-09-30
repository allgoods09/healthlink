<?php

namespace App\Models;

use App\Support\Nutrition\OptCycleRules;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class OptCycle extends Model
{
    public const IN_PROGRESS = 'in_progress';

    public const COMPLETED = 'completed';

    protected $guarded = ['id'];

    protected $casts = [
        'reference_date' => 'date',
        'roster_captured_at' => 'datetime',
        'completed_at' => 'datetime',
        'reopened_at' => 'datetime',
        'provenance' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $cycle): void {
            if ($cycle->isDirty(['barangay_id', 'year', 'round', 'reference_date', 'roster_captured_at'])) {
                throw new LogicException('Captured OPT cycle identity and reference date are immutable.');
            }
        });
    }

    public function entries()
    {
        return $this->hasMany(OptCycleEntry::class);
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class)->withTrashed();
    }

    public function getTitleAttribute(): string
    {
        return (OptCycleRules::ROUNDS[$this->round] ?? $this->round).' '.$this->year.' OPT+';
    }

    public function scopeWithProgress($query)
    {
        return $query->withCount(['entries', 'entries as measured_count' => fn ($q) => $q->whereHas('measurement')]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::IN_PROGRESS);
    }

    public function getUnmeasuredCountAttribute(): int
    {
        return $this->entries_count - $this->measured_count;
    }

    public function getCoverageAttribute(): float
    {
        return $this->entries_count ? round($this->measured_count / $this->entries_count * 100, 1) : 0;
    }
}
