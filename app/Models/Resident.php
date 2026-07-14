<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\AuditLog;
use Carbon\CarbonInterface;

class Resident extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'official_resident_code',
        'mobile_uuid',
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
        'resident_status',
        'moved_in_at',
        'moved_out_at',
        'date_of_death',
        'status_notes',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'birth_date' => 'date',
        'moved_in_at' => 'date',
        'moved_out_at' => 'date',
        'date_of_death' => 'date',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (Resident $resident): void {
            if ($resident->official_resident_code || ! $resident->household_id) {
                return;
            }

            $barangayId = Household::query()
                ->whereKey($resident->household_id)
                ->join('puroks', 'puroks.id', '=', 'households.purok_id')
                ->value('puroks.barangay_id');

            if (! $barangayId) {
                return;
            }

            $sequence = static::query()
                ->whereNotNull('official_resident_code')
                ->whereHas('household.purok', fn ($query) => $query->where('barangay_id', $barangayId))
                ->count() + 1;

            $resident->forceFill([
                'official_resident_code' => sprintf('RS-%04d-%05d', $barangayId, $sequence),
            ])->saveQuietly();
        });
    }

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DECEASED = 'deceased';
    public const STATUS_RELOCATED = 'relocated';

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

    /**
     * Get the resident's socio-economic profile.
     */
    public function socioEconomicProfile()
    {
        return $this->hasOne(ResidentSocioEconomicProfile::class, 'resident_id');
    }

    /**
     * Get all certificates issued to this resident.
     */
    public function certificates()
    {
        return $this->hasMany(BarangayCertificate::class);
    }

    /**
     * Get all draft records resolved into this resident.
     */
    public function draftApprovals()
    {
        return $this->hasMany(ResidentDraft::class, 'approved_resident_id');
    }

    /**
     * Get all pending update requests for this resident.
     */
    public function profileUpdateRequests()
    {
        return $this->hasMany(ProfileUpdateRequest::class, 'subject_id')
            ->where('subject_type', ProfileUpdateRequest::SUBJECT_RESIDENT);
    }

    /**
     * Get all queued triage records for this resident.
     */
    public function triageRecords()
    {
        return $this->hasMany(TriageRecord::class);
    }

    public function optMeasurements()
    {
        return $this->hasMany(OptMeasurement::class);
    }

    public function latestOptMeasurement()
    {
        return $this->hasOne(OptMeasurement::class)->ofMany([
            'measurement_date' => 'max',
            'id' => 'max',
        ]);
    }

    public function nutritionFlags()
    {
        return $this->hasMany(ChildNutritionAssessmentFlag::class);
    }

    public function maternalNutritionProfile()
    {
        return $this->hasOne(MaternalNutritionProfile::class);
    }

    public function maternalNutritionHistories()
    {
        return $this->hasMany(MaternalNutritionHistory::class);
    }

    public function infantFeedingLogs()
    {
        return $this->hasMany(InfantFeedingLog::class);
    }

    public function receivedSupplementationLogs()
    {
        return $this->hasMany(MicronutrientSupplementationLog::class);
    }

    public function feedingProgramEnrollments()
    {
        return $this->hasMany(FeedingProgramEnrollment::class);
    }

    public function communityCampaignAssignments()
    {
        return $this->hasMany(CommunityCampaignAssignment::class);
    }

    public function clinicalEncounters()
    {
        return $this->hasMany(ClinicalEncounter::class);
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
     * Scope a query to only include residents with a specific civil status.
     */
    public function scopeWithResidentStatus($query, string $status)
    {
        return $query->where('resident_status', $status);
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

    public function ageInMonthsAt(CarbonInterface $date): int
    {
        if (! $this->birth_date) {
            return 0;
        }

        return $this->birth_date->copy()->startOfDay()->diffInMonths($date->copy()->startOfDay());
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

    /**
     * Get the resident's civil registry status label.
     */
    public function getResidentStatusLabelAttribute(): string
    {
        return match ($this->resident_status) {
            self::STATUS_ACTIVE => 'Active Resident',
            self::STATUS_DECEASED => 'Deceased',
            self::STATUS_RELOCATED => 'Relocated',
            default => 'Unknown',
        };
    }

    /**
     * Determine whether this resident is the strict household head.
     */
    public function getIsHouseholdHeadAttribute(): bool
    {
        return (int) $this->household?->head_resident_id === (int) $this->id;
    }

    // =============================================
    // HELPER METHODS
    // =============================================

    /**
     * Check if the resident is active.
     */
    public function isActive(): bool
    {
        return $this->is_active
            && $this->resident_status === self::STATUS_ACTIVE
            && ! $this->trashed();
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
     * Check if the resident is marked as deceased.
     */
    public function isDeceased(): bool
    {
        return $this->resident_status === self::STATUS_DECEASED;
    }

    /**
     * Check if the resident is marked as relocated.
     */
    public function isRelocated(): bool
    {
        return $this->resident_status === self::STATUS_RELOCATED;
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
