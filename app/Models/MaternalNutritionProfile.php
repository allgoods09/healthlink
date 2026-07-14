<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaternalNutritionProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'resident_id',
        'barangay_id',
        'updated_by_user_id',
        'is_currently_pregnant',
        'is_currently_lactating',
        'expected_delivery_date',
        'current_risk_notes',
        'last_status_updated_at',
    ];

    protected $casts = [
        'is_currently_pregnant' => 'boolean',
        'is_currently_lactating' => 'boolean',
        'expected_delivery_date' => 'date',
        'last_status_updated_at' => 'datetime',
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

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function histories()
    {
        return $this->hasMany(MaternalNutritionHistory::class, 'resident_id', 'resident_id');
    }

    public function getStatusSummaryAttribute(): string
    {
        return collect([
            $this->is_currently_pregnant ? 'Pregnant' : null,
            $this->is_currently_lactating ? 'Lactating' : null,
        ])->filter()->join(' / ') ?: 'No active maternal flag';
    }
}
