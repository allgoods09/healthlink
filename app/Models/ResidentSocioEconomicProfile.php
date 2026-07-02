<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidentSocioEconomicProfile extends Model
{
    use HasFactory;

    // Directives for non-standard primary keys
    protected $primaryKey = 'resident_id';
    public $incrementing = false;

    protected $fillable = [
        'resident_id',
        'occupation',
        'employment_status',
        'highest_education_level',
        'education_status',
        'is_pwd',
        'disability_type',
        'is_ofw',
        'is_solo_parent',
        'is_osy',
        'is_osc',
        'is_ip',
        'ethnicity',
    ];

    // Converts database fields instantly to clean frontend booleans
    protected $casts = [
        'is_pwd' => 'boolean',
        'is_ofw' => 'boolean',
        'is_solo_parent' => 'boolean',
        'is_osy' => 'boolean',
        'is_osc' => 'boolean',
        'is_ip' => 'boolean',
    ];

    /**
     * Get the resident record associated with this profile.
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'resident_id');
    }
}