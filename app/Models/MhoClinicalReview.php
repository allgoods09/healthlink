<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MhoClinicalReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinical_encounter_id',
        'reviewed_by_user_id',
        'reviewed_at',
        'final_assessment',
        'diagnostic_override',
        'prescription_notes',
        'referral_destination',
        'final_disposition',
        'return_instructions',
        'resolution_notes',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function clinicalEncounter()
    {
        return $this->belongsTo(ClinicalEncounter::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
