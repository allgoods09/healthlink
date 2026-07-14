<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Household extends Model
{
    use HasFactory, SoftDeletes;

    public const GARBAGE_DISPOSAL_METHODS = [
        'burning' => 'Burning',
        'dumping' => 'Dumping',
        'collection' => 'Collection',
    ];

    public const HOUSING_MATERIAL_TYPES = [
        'light' => 'Light',
        'concrete' => 'Concrete',
        'mixed' => 'Mixed',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'official_household_code',
        'mobile_uuid',
        'purok_id',
        'household_no',
        'household_address',
        'drinking_water_source',
        'has_sanitary_toilet',
        'sanitary_toilet_type',
        'garbage_disposal_method',
        'has_backyard_garden',
        'housing_material_type',
        'head_resident_id',
        'is_social_aid_beneficiary',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'has_sanitary_toilet' => 'boolean',
        'has_backyard_garden' => 'boolean',
        'is_social_aid_beneficiary' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (Household $household): void {
            if ($household->official_household_code || ! $household->purok_id) {
                return;
            }

            $barangayId = $household->purok()->value('barangay_id');

            if (! $barangayId) {
                return;
            }

            $sequence = static::query()
                ->whereNotNull('official_household_code')
                ->whereHas('purok', fn ($query) => $query->where('barangay_id', $barangayId))
                ->count() + 1;

            $household->forceFill([
                'official_household_code' => sprintf('HH-%04d-%05d', $barangayId, $sequence),
            ])->saveQuietly();
        });
    }

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

    /**
     * Get the designated head resident for this household.
     */
    public function headResident()
    {
        return $this->belongsTo(Resident::class, 'head_resident_id');
    }

    /**
     * Get all field visits recorded for this household.
     */
    public function fieldVisits()
    {
        return $this->hasMany(FieldVisit::class);
    }

    /**
     * Get all certificates issued to this household.
     */
    public function certificates()
    {
        return $this->hasMany(BarangayCertificate::class);
    }

    /**
     * Get all draft packages that resolved into this household.
     */
    public function draftPackages()
    {
        return $this->hasMany(HouseholdDraft::class, 'approved_household_id');
    }

    /**
     * Get all pending update requests for this household.
     */
    public function profileUpdateRequests()
    {
        return $this->hasMany(ProfileUpdateRequest::class, 'subject_id')
            ->where('subject_type', ProfileUpdateRequest::SUBJECT_HOUSEHOLD);
    }

    /**
     * Get all triage records tied to this household.
     */
    public function triageRecords()
    {
        return $this->hasMany(TriageRecord::class);
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
        if ($this->relationLoaded('headResident')) {
            return $this->headResident;
        }

        return $this->headResident()->first();
    }

    /**
     * Get the total number of residents in this household.
     */
    public function getResidentCountAttribute(): int
    {
        return $this->residents()->count();
    }

    public function getGarbageDisposalMethodLabelAttribute(): string
    {
        return self::GARBAGE_DISPOSAL_METHODS[$this->garbage_disposal_method] ?? 'Not Set';
    }

    public function getHousingMaterialTypeLabelAttribute(): string
    {
        return self::HOUSING_MATERIAL_TYPES[$this->housing_material_type] ?? 'Not Set';
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
