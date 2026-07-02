<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purok extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'barangay_id',
        'purok_number',
        'purok_name',
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
     * Get the barangay that owns this purok.
     */
    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    /**
     * Get all households in this purok.
     */
    public function households()
    {
        return $this->hasMany(Household::class);
    }

    /**
     * Get all users assigned to this purok (BHWs only).
     */
    public function assignedUsers()
    {
        return $this->hasMany(User::class, 'assigned_purok_id');
    }

    // =============================================
    // SCOPES
    // =============================================

    /**
     * Scope a query to only include active puroks.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive puroks.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope a query to search by purok number or name.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('purok_number', $search)
                     ->orWhere('purok_name', 'LIKE', "%{$search}%");
    }

    // =============================================
    // ACCESSORS
    // =============================================

    /**
     * Get the full purok display name.
     */
    public function getDisplayNameAttribute(): string
    {
        $name = "Purok {$this->purok_number}";
        if ($this->purok_name) {
            $name .= " - {$this->purok_name}";
        }
        return $name;
    }

    /**
     * Get the full location with barangay.
     */
    public function getFullLocationAttribute(): string
    {
        return "{$this->barangay->name} - Purok {$this->purok_number}";
    }

    // =============================================
    // HELPER METHODS
    // =============================================

    /**
     * Check if the purok is active.
     */
    public function isActive(): bool
    {
        return $this->is_active && !$this->trashed();
    }

    /**
     * Get the total number of households in this purok.
     */
    public function getTotalHouseholdsAttribute(): int
    {
        return $this->households()->count();
    }

    /**
     * Get the total number of residents in this purok.
     */
    public function getTotalResidentsAttribute(): int
    {
        return $this->households()->withCount('residents')->get()->sum('residents_count');
    }
}