<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Barangay extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'psgc_code',
        'municipality',
        'province',
        'region',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // =============================================
    // RELATIONSHIPS
    // =============================================

    /**
     * Get all puroks in this barangay.
     */
    public function puroks()
    {
        return $this->hasMany(Purok::class);
    }

    /**
     * Get all users assigned to this barangay.
     */
    public function assignedUsers()
    {
        return $this->hasMany(User::class, 'assigned_barangay_id');
    }

    // =============================================
    // SCOPES
    // =============================================

    /**
     * Scope a query to only include active barangays.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive barangays.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope a query to search by name or PSGC code.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'LIKE', "%{$search}%")
                     ->orWhere('psgc_code', 'LIKE', "%{$search}%");
    }

    // =============================================
    // ACCESSORS
    // =============================================

    /**
     * Get the full geographic address.
     */
    public function getFullAddressAttribute(): string
    {
        return "{$this->name}, {$this->municipality}, {$this->province}, Region {$this->region}";
    }

    /**
     * Get the display name with PSGC code.
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->psgc_code})";
    }

    // =============================================
    // HELPER METHODS
    // =============================================

    /**
     * Check if the barangay is active.
     */
    public function isActive(): bool
    {
        return $this->is_active && !$this->trashed();
    }

    /**
     * Get the total number of households in this barangay.
     */
    public function getTotalHouseholdsAttribute(): int
    {
        return $this->puroks()->withCount('households')->get()->sum('households_count');
    }

    /**
     * Get the total number of residents in this barangay.
     */
    public function getTotalResidentsAttribute(): int
    {
        return Household::whereHas('purok', function ($query) {
            $query->where('barangay_id', $this->id);
        })->withCount('residents')->get()->sum('residents_count');
    }
}