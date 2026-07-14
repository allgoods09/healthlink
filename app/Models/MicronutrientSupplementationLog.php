<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MicronutrientSupplementationLog extends Model
{
    use HasFactory;

    public const TYPE_VITAMIN_A = 'vitamin_a';
    public const TYPE_IRON_DROPS = 'iron_drops';
    public const TYPE_MNP = 'mnp';

    public const RECIPIENT_TODDLER = 'toddler';
    public const RECIPIENT_PREGNANT_WOMAN = 'pregnant_woman';
    public const RECIPIENT_LACTATING_MOTHER = 'lactating_mother';

    public const SUPPLEMENT_TYPES = [
        self::TYPE_VITAMIN_A => 'Vitamin A',
        self::TYPE_IRON_DROPS => 'Iron Drops',
        self::TYPE_MNP => 'Micronutrient Powder (MNP)',
    ];

    public const RECIPIENT_CATEGORIES = [
        self::RECIPIENT_TODDLER => 'Toddler / Young Child',
        self::RECIPIENT_PREGNANT_WOMAN => 'Pregnant Woman',
        self::RECIPIENT_LACTATING_MOTHER => 'Lactating Mother',
    ];

    protected $fillable = [
        'resident_id',
        'barangay_id',
        'distributed_by_user_id',
        'administered_on',
        'supplement_type',
        'recipient_category',
        'dose_description',
        'remarks',
    ];

    protected $casts = [
        'administered_on' => 'date',
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

    public function distributedBy()
    {
        return $this->belongsTo(User::class, 'distributed_by_user_id');
    }

    public function getSupplementTypeLabelAttribute(): string
    {
        return self::SUPPLEMENT_TYPES[$this->supplement_type] ?? str((string) $this->supplement_type)->replace('_', ' ')->title()->toString();
    }

    public function getRecipientCategoryLabelAttribute(): string
    {
        return self::RECIPIENT_CATEGORIES[$this->recipient_category] ?? str((string) $this->recipient_category)->replace('_', ' ')->title()->toString();
    }
}
