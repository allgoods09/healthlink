<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\AuditLog;

class Resident extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'household_id',
        'philsys_card_no',
        'last_name',
        'first_name',
        'middle_name',
        'suffix',
        'birth_date',
        'birth_place',
        'sex',
        'civil_status',
        'citizenship',
        'religion',
        'contact_number',
        'email_address',
        'relationship_to_head',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'birth_date' => 'date',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // =============================================
    // RELATIONSHIPS
    // =============================================

    /**
     * Get the household that owns this resident.
     */
    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Get the audit logs related to this resident.
     */
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'model');
    }

    // =============================================
    // SCOPES
    // =============================================

    /**
     * Scope a query to only include active residents.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive residents.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope a query to search by name.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('first_name', 'LIKE', "%{$search}%")
                     ->orWhere('last_name', 'LIKE', "%{$search}%")
                     ->orWhere('middle_name', 'LIKE', "%{$search}%")
                     ->orWhere('philsys_card_no', 'LIKE', "%{$search}%");
    }

    /**
     * Scope a query to only include males.
     */
    public function scopeMale($query)
    {
        return $query->where('sex', 'Male');
    }

    /**
     * Scope a query to only include females.
     */
    public function scopeFemale($query)
    {
        return $query->where('sex', 'Female');
    }

    /**
     * Scope a query to only include residents of a specific age range.
     */
    public function scopeAgeRange($query, $min, $max)
    {
        $minDate = now()->subYears($max)->startOfDay();
        $maxDate = now()->subYears($min)->endOfDay();
        
        return $query->whereBetween('birth_date', [$minDate, $maxDate]);
    }

    /**
     * Scope a query to only include children (0-5 years old).
     */
    public function scopeChildren($query)
    {
        return $query->ageRange(0, 5);
    }

    /**
     * Scope a query to only include adults (18+ years old).
     */
    public function scopeAdults($query)
    {
        return $query->ageRange(18, 120);
    }

    // =============================================
    // ACCESSORS
    // =============================================

    /**
     * Get the resident's full name.
     */
    public function getFullNameAttribute(): string
    {
        $name = "{$this->first_name} {$this->last_name}";
        if ($this->middle_name) {
            $name = "{$this->first_name} {$this->middle_name} {$this->last_name}";
        }
        if ($this->suffix) {
            $name .= " {$this->suffix}";
        }
        return $name;
    }

    /**
     * Get the resident's full name with suffix.
     */
    public function getFullNameWithSuffixAttribute(): string
    {
        $name = $this->full_name;
        if ($this->suffix) {
            $name .= " {$this->suffix}";
        }
        return $name;
    }

    /**
     * Get the resident's formatted name (Last, First Middle).
     */
    public function getFormalNameAttribute(): string
    {
        $name = "{$this->last_name}, {$this->first_name}";
        if ($this->middle_name) {
            $name .= " {$this->middle_name}";
        }
        if ($this->suffix) {
            $name .= " {$this->suffix}";
        }
        return $name;
    }

    /**
     * Get the resident's age in years.
     */
    public function getAgeAttribute(): int
    {
        return $this->birth_date ? $this->birth_date->age : 0;
    }

    /**
     * Get the resident's age in months (for children).
     */
    public function getAgeInMonthsAttribute(): int
    {
        if (!$this->birth_date) {
            return 0;
        }
        
        $diff = now()->diff($this->birth_date);
        return ($diff->y * 12) + $diff->m;
    }

    /**
     * Get the resident's age group category.
     */
    public function getAgeGroupAttribute(): string
    {
        $age = $this->age;
        
        if ($age < 1) {
            return 'Infant';
        } elseif ($age < 5) {
            return 'Toddler';
        } elseif ($age < 13) {
            return 'Child';
        } elseif ($age < 18) {
            return 'Adolescent';
        } elseif ($age < 60) {
            return 'Adult';
        } else {
            return 'Senior Citizen';
        }
    }

    // =============================================
    // HELPER METHODS
    // =============================================

    /**
     * Check if the resident is active.
     */
    public function isActive(): bool
    {
        return $this->is_active && !$this->trashed();
    }

    /**
     * Check if the resident is a child (0-5 years old).
     */
    public function isChild(): bool
    {
        return $this->age <= 5;
    }

    /**
     * Check if the resident is a senior citizen (60+ years old).
     */
    public function isSenior(): bool
    {
        return $this->age >= 60;
    }

    /**
     * Get the resident's complete address.
     */
    public function getCompleteAddressAttribute(): string
    {
        return $this->household->household_address ?? 'No address available';
    }

    /**
     * Get the resident's location hierarchy.
     */
    public function getLocationHierarchyAttribute(): array
    {
        return [
            'barangay' => $this->household->purok->barangay->name ?? null,
            'purok' => $this->household->purok->display_name ?? null,
            'household' => $this->household->full_identifier ?? null,
        ];
    }
}