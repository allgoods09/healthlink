<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedingProgramAttendance extends Model
{
    use HasFactory;

    public const STATUS_PRESENT = 'present';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_EXCUSED = 'excused';

    public const STATUSES = [
        self::STATUS_PRESENT => 'Present',
        self::STATUS_ABSENT => 'Absent',
        self::STATUS_EXCUSED => 'Excused',
    ];

    protected $fillable = [
        'enrollment_id',
        'attendance_date',
        'attendance_status',
        'notes',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function enrollment()
    {
        return $this->belongsTo(FeedingProgramEnrollment::class, 'enrollment_id');
    }

    public function getAttendanceStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->attendance_status] ?? str((string) $this->attendance_status)->replace('_', ' ')->title()->toString();
    }
}
