<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Household extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'purok_id',
        'household_no',
        'household_address',
        'is_social_aid_beneficiary',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_social_aid_beneficiary' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // =============================================
    // RELATIONSHIPS
    // =============================================

    /**
     * Get the purok that owns this household.
     */
    public function purok()
    {
        return $this->belongsTo(Purok::class);
    }

    /**
     * Get all residents in this household.
     */
    public function residents()
    {
        return $this->hasMany(Resident::class);
    }

    // =============================================
    // SCOPES
    // =============================================

    /**
     * Scope a query to only include active households.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive households.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope a query to only include households with social aid.
     */
    public function scopeWithSocialAid($query)
    {
        return $query->where('is_social_aid_beneficiary', true);
    }

    /**
     * Scope a query to search by household number.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('household_no', 'LIKE', "%{$search}%");
    }

    // =============================================
    // ACCESSORS
    // =============================================

    /**
     * Get the full household identifier.
     */
    public function getFullIdentifierAttribute(): string
    {
        return "Household #{$this->household_no} - Purok {$this->purok->purok_number}";
    }

    /**
     * Get the household head name if available.
     */
    public function getHeadOfHouseholdAttribute()
    {
        // Assuming the first resident with relationship "Head" is the head
        return $this->residents()
                    ->where('relationship_to_head', 'Head')
                    ->first();
    }

    /**
     * Get the total number of residents in this household.
     */
    public function getResidentCountAttribute(): int
    {
        return $this->residents()->count();
    }

    // =============================================
    // HELPER METHODS
    // =============================================

    /**
     * Check if the household is active.
     */
    public function isActive(): bool
    {
        return $this->is_active && !$this->trashed();
    }

    /**
     * Get residents with their full names.
     */
    public function getResidentsWithFullNames()
    {
        return $this->residents()->get()->map(function ($resident) {
            return [
                'id' => $resident->id,
                'full_name' => $resident->full_name,
                'relationship' => $resident->relationship_to_head,
            ];
        });
    }
}