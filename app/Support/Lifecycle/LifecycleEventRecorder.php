<?php

namespace App\Support\Lifecycle;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\ResidentLifecycleEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use LogicException;

class LifecycleEventRecorder
{
    /** Context is explicit: optional barangay, purok and household models, never inferred from the resident. */
    public function record(Resident $resident, string $type, string $key, ?User $actor = null,
        ?string $effectiveDate = null, array $source = [], array $destination = [],
        ?string $remarks = null, array $metadata = [], ?string $provenance = null): ResidentLifecycleEvent
    {
        Validator::make(['type' => $type, 'key' => $key, 'date' => $effectiveDate, 'provenance' => $provenance], [
            'type' => ['required', Rule::in(ResidentLifecycleEvent::TYPES)], 'key' => ['required', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'], 'provenance' => ['nullable', 'string', 'max:100'],
        ])->validate();
        if (! $resident->exists || ! $resident->getKey()) {
            throw new LogicException('A persisted resident is required.');
        }
        if ($actor && (! $actor->exists || ! $actor->getKey())) {
            throw new LogicException('A persisted actor is required.');
        }
        $attributes = ['resident_id' => $resident->id, 'event_type' => $type, 'event_key' => $key,
            'effective_date' => $effectiveDate, 'actor_user_id' => $actor?->id,
            'actor_name_snapshot' => $actor?->display_name, 'actor_role_snapshot' => $actor?->role,
            'remarks' => $remarks, 'metadata' => $metadata, 'provenance' => $provenance,
            ...$this->context('source', $source), ...$this->context('destination', $destination)];
        $connection = $resident->getConnection();
        $query = fn () => ResidentLifecycleEvent::on($connection->getName())->where('resident_id', $resident->id)->where('event_key', $key);
        $create = function () use ($query, $attributes, $connection): ResidentLifecycleEvent {
            if ($existing = $query()->first()) {
                return $this->compatible($existing, $attributes);
            }
            $event = new ResidentLifecycleEvent;
            $event->setConnection($connection->getName());
            $event->forceFill([...$attributes, 'recorded_at' => CarbonImmutable::now('UTC')])->save();

            return $event->refresh();
        };
        try {
            return $connection->transaction($create, 5);
        } catch (UniqueConstraintViolationException $exception) {
            // The competing insert won. Never overwrite its original snapshots or recording instant.
            $existing = $query()->lockForUpdate()->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->compatible($existing, $attributes);
        }
    }

    private function context(string $side, array $context): array
    {
        if (array_diff(array_keys($context), ['barangay', 'purok', 'household'])) {
            throw new LogicException('Unsupported lifecycle context.');
        }
        foreach (['barangay' => Barangay::class, 'purok' => Purok::class, 'household' => Household::class] as $key => $class) {
            if (isset($context[$key]) && (! $context[$key] instanceof $class || ! $context[$key]->exists)) {
                throw new LogicException('Lifecycle context must contain persisted records.');
            }
        }
        $barangay = $context['barangay'] ?? null;
        $purok = $context['purok'] ?? null;
        $household = $context['household'] ?? null;
        if (($barangay && $purok && (int) $purok->barangay_id !== (int) $barangay->id)
            || ($household && $purok && (int) $household->purok_id !== (int) $purok->id)) {
            throw new LogicException('Inconsistent lifecycle context.');
        }

        return [$side.'_barangay_id' => $barangay?->id, $side.'_barangay_name_snapshot' => $barangay?->name,
            $side.'_purok_id' => $purok?->id, $side.'_purok_number_snapshot' => $purok?->purok_number,
            $side.'_purok_name_snapshot' => $purok?->purok_name, $side.'_household_id' => $household?->id,
            $side.'_household_no_snapshot' => $household?->household_no];
    }

    private function compatible(ResidentLifecycleEvent $event, array $attributes): ResidentLifecycleEvent
    {
        foreach ($attributes as $field => $value) {
            // Related records may have been renamed since the original call; history stays frozen.
            if (str_ends_with($field, '_snapshot')) {
                continue;
            }
            $stored = $field === 'effective_date' ? $event->effective_date?->format('Y-m-d') : $event->{$field};
            if (json_encode($this->canonical($stored), JSON_THROW_ON_ERROR) !== json_encode($this->canonical($value), JSON_THROW_ON_ERROR)) {
                throw new LogicException('Lifecycle event key already exists with incompatible facts.');
            }
        }

        return $event;
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map($this->canonical(...), $value);
    }
}
