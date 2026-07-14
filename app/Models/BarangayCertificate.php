<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BarangayCertificate extends Model
{
    use HasFactory;

    public const TYPE_CLEARANCE = 'barangay_clearance';
    public const TYPE_INDIGENCY = 'certificate_of_indigency';

    public const RECIPIENT_RESIDENT = 'resident';
    public const RECIPIENT_HOUSEHOLD = 'household';

    protected $fillable = [
        'barangay_id',
        'certificate_type',
        'recipient_type',
        'resident_id',
        'household_id',
        'certificate_no',
        'issued_to_name',
        'purpose',
        'remarks',
        'issued_at',
        'issued_by_user_id',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function getCertificateTypeLabelAttribute(): string
    {
        return match ($this->certificate_type) {
            self::TYPE_CLEARANCE => 'Barangay Clearance',
            self::TYPE_INDIGENCY => 'Certificate of Indigency',
            default => str((string) $this->certificate_type)->replace('_', ' ')->title()->toString(),
        };
    }

    public function getRecipientTypeLabelAttribute(): string
    {
        return match ($this->recipient_type) {
            self::RECIPIENT_RESIDENT => 'Resident',
            self::RECIPIENT_HOUSEHOLD => 'Household',
            default => str((string) $this->recipient_type)->replace('_', ' ')->title()->toString(),
        };
    }

    public function getRecipientSummaryAttribute(): string
    {
        if ($this->recipient_type === self::RECIPIENT_RESIDENT && $this->resident) {
            return $this->resident->formal_name;
        }

        if ($this->recipient_type === self::RECIPIENT_HOUSEHOLD && $this->household) {
            return 'Household #'.$this->household->household_no;
        }

        return $this->issued_to_name;
    }
}
