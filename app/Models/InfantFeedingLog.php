<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InfantFeedingLog extends Model
{
    use HasFactory;

    public const METHOD_EXCLUSIVE_BREASTFEEDING = 'exclusive_breastfeeding';
    public const METHOD_MIXED_FEEDING = 'mixed_feeding';
    public const METHOD_FORMULA = 'formula';

    public const METHODS = [
        self::METHOD_EXCLUSIVE_BREASTFEEDING => 'Exclusive Breastfeeding',
        self::METHOD_MIXED_FEEDING => 'Mixed Feeding',
        self::METHOD_FORMULA => 'Formula',
    ];

    protected $fillable = [
        'resident_id',
        'mother_resident_id',
        'recorded_by_user_id',
        'observed_on',
        'feeding_method',
        'notes',
    ];

    protected $casts = [
        'observed_on' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function mother()
    {
        return $this->belongsTo(Resident::class, 'mother_resident_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function getFeedingMethodLabelAttribute(): string
    {
        return self::METHODS[$this->feeding_method] ?? str((string) $this->feeding_method)->replace('_', ' ')->title()->toString();
    }
}
