<?php

namespace App\Models;

use App\Support\Lifecycle\AppendOnlyLifecycleEventBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class ResidentLifecycleEvent extends Model
{
    public const REGISTRY_CAPTURE = 'registry_capture';

    public const RESIDENT_REGISTERED = 'resident_registered';

    public const HOUSEHOLD_CHANGED = 'household_changed';

    public const BARANGAY_TRANSFERRED = 'barangay_transferred';

    public const MOVED_OUT_OF_MUNICIPALITY = 'moved_out_of_municipality';

    public const RETURNED_TO_MUNICIPALITY = 'returned_to_municipality';

    public const MARKED_DECEASED = 'marked_deceased';

    public const TYPES = [self::REGISTRY_CAPTURE, self::RESIDENT_REGISTERED, self::HOUSEHOLD_CHANGED,
        self::BARANGAY_TRANSFERRED, self::MOVED_OUT_OF_MUNICIPALITY, self::RETURNED_TO_MUNICIPALITY, self::MARKED_DECEASED];

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $casts = ['recorded_at' => 'datetime', 'effective_date' => 'date', 'metadata' => 'array'];

    public function newEloquentBuilder($query)
    {
        return new AppendOnlyLifecycleEventBuilder($query);
    }

    public function save(array $options = [])
    {
        if ($this->exists) {
            throw new LogicException('Resident lifecycle events are append-only.');
        }

        return parent::save($options);
    }

    public function update(array $attributes = [], array $options = [])
    {
        throw new LogicException('Resident lifecycle events are append-only.');
    }

    public function updateQuietly(array $attributes = [], array $options = [])
    {
        throw new LogicException('Resident lifecycle events are append-only.');
    }

    public function delete()
    {
        throw new LogicException('Resident lifecycle events are append-only.');
    }

    public function getRecordedAtAttribute($value): ?CarbonImmutable
    {
        // DATETIME has no timezone; this column always represents a UTC instant.
        return $value === null ? null : CarbonImmutable::parse($value, 'UTC');
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withTrashed();
    }

    public function sourceBarangay()
    {
        return $this->belongsTo(Barangay::class, 'source_barangay_id')->withTrashed();
    }

    public function destinationBarangay()
    {
        return $this->belongsTo(Barangay::class, 'destination_barangay_id')->withTrashed();
    }

    public function sourcePurok()
    {
        return $this->belongsTo(Purok::class, 'source_purok_id')->withTrashed();
    }

    public function destinationPurok()
    {
        return $this->belongsTo(Purok::class, 'destination_purok_id')->withTrashed();
    }

    public function sourceHousehold()
    {
        return $this->belongsTo(Household::class, 'source_household_id')->withTrashed();
    }

    public function destinationHousehold()
    {
        return $this->belongsTo(Household::class, 'destination_household_id')->withTrashed();
    }
}
