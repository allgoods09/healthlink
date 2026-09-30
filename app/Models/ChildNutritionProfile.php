<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChildNutritionProfile extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'caregiver_confirmed_at' => 'datetime',
        'ip_confirmed_at' => 'datetime',
        'ip_membership' => 'boolean',
    ];

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function caregiver()
    {
        return $this->belongsTo(Resident::class, 'caregiver_resident_id');
    }
}
