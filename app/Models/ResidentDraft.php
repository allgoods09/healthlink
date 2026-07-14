<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResidentDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'household_draft_id',
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
        'is_household_head_candidate',
        'draft_notes',
        'approved_resident_id',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'is_household_head_candidate' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function householdDraft()
    {
        return $this->belongsTo(HouseholdDraft::class);
    }

    public function approvedResident()
    {
        return $this->belongsTo(Resident::class, 'approved_resident_id');
    }

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
}
