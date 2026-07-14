<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HouseholdDraft extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'submitted_by_user_id',
        'barangay_id',
        'purok_id',
        'draft_reference_code',
        'household_address',
        'drinking_water_source',
        'has_sanitary_toilet',
        'sanitary_toilet_type',
        'garbage_disposal_method',
        'has_backyard_garden',
        'housing_material_type',
        'is_social_aid_beneficiary',
        'draft_status',
        'reviewed_by_user_id',
        'reviewed_at',
        'verification_notes',
        'approved_household_id',
    ];

    protected $casts = [
        'has_sanitary_toilet' => 'boolean',
        'has_backyard_garden' => 'boolean',
        'is_social_aid_beneficiary' => 'boolean',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (HouseholdDraft $householdDraft): void {
            if ($householdDraft->draft_reference_code || ! $householdDraft->barangay_id) {
                return;
            }

            $sequence = static::query()
                ->where('barangay_id', $householdDraft->barangay_id)
                ->count() + 1;

            $householdDraft->draft_reference_code = sprintf('HD-%04d-%05d', $householdDraft->barangay_id, $sequence);
        });
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function purok()
    {
        return $this->belongsTo(Purok::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function approvedHousehold()
    {
        return $this->belongsTo(Household::class, 'approved_household_id');
    }

    public function residentDrafts()
    {
        return $this->hasMany(ResidentDraft::class);
    }

    public function getGarbageDisposalMethodLabelAttribute(): string
    {
        return Household::GARBAGE_DISPOSAL_METHODS[$this->garbage_disposal_method] ?? 'Not Set';
    }

    public function getHousingMaterialTypeLabelAttribute(): string
    {
        return Household::HOUSING_MATERIAL_TYPES[$this->housing_material_type] ?? 'Not Set';
    }

    public function scopePending($query)
    {
        return $query->where('draft_status', self::STATUS_PENDING);
    }

    public function getDraftStatusLabelAttribute(): string
    {
        return match ($this->draft_status) {
            self::STATUS_PENDING => 'Pending Review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            default => 'Unknown',
        };
    }
}
