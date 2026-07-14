<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedingProgramEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'feeding_program_id',
        'resident_id',
        'enrolled_by_user_id',
        'enrolled_on',
        'baseline_weight_kg',
        'baseline_nutritional_status',
        'is_active',
        'completion_notes',
    ];

    protected $casts = [
        'enrolled_on' => 'date',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function feedingProgram()
    {
        return $this->belongsTo(FeedingProgram::class);
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function enrolledBy()
    {
        return $this->belongsTo(User::class, 'enrolled_by_user_id');
    }

    public function attendances()
    {
        return $this->hasMany(FeedingProgramAttendance::class, 'enrollment_id');
    }

    public function progressLogs()
    {
        return $this->hasMany(FeedingProgramProgressLog::class, 'enrollment_id');
    }

    public function latestProgressLog()
    {
        return $this->hasOne(FeedingProgramProgressLog::class, 'enrollment_id')->latestOfMany('logged_on');
    }

    public function getEnrollmentStatusLabelAttribute(): string
    {
        return $this->is_active ? 'Active' : 'Completed';
    }
}
