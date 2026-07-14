<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChildNutritionAssessmentFlag extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'resident_id',
        'barangay_id',
        'purok_id',
        'flagged_by_user_id',
        'closed_by_user_id',
        'resolved_measurement_id',
        'flag_status',
        'flag_reason',
        'flagged_at',
        'closed_at',
    ];

    protected $casts = [
        'flagged_at' => 'datetime',
        'closed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function purok()
    {
        return $this->belongsTo(Purok::class);
    }

    public function flaggedBy()
    {
        return $this->belongsTo(User::class, 'flagged_by_user_id');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function resolvedMeasurement()
    {
        return $this->belongsTo(OptMeasurement::class, 'resolved_measurement_id');
    }
}
