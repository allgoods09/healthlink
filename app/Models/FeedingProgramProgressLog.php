<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedingProgramProgressLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
        'logged_by_user_id',
        'logged_on',
        'week_number',
        'weight_kg',
        'remarks',
    ];

    protected $casts = [
        'logged_on' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function enrollment()
    {
        return $this->belongsTo(FeedingProgramEnrollment::class, 'enrollment_id');
    }

    public function loggedBy()
    {
        return $this->belongsTo(User::class, 'logged_by_user_id');
    }
}
