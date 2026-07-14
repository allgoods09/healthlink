<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FieldVisit extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'mobile_uuid',
        'household_id',
        'recorded_by_user_id',
        'visited_at',
        'notes',
        'photos',
        'source',
        'last_synced_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'photos' => 'array',
        'visited_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the household linked to this field visit.
     */
    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Get the BHW who recorded the field visit.
     */
    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /**
     * Get a quick count of attached photos.
     */
    public function getPhotoCountAttribute(): int
    {
        return count($this->photos ?? []);
    }
}
