<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Resident;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class HouseholdHeadManager
{
    public function locked(array $householdIds, callable $operation): mixed
    {
        return DB::transaction(function () use ($householdIds, $operation) {
            $households = Household::query()->with('purok')->whereIn('id', array_unique($householdIds))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($householdIds as $id) {
                abort_unless($households->has($id), 404);
                Gate::authorize('update', $households[$id]);
            }
            $members = Resident::query()->whereIn('household_id', $householdIds)->orderBy('id')->lockForUpdate()->get();
            foreach ($households as $household) {
                $household->setRelation('residents', $members->where('household_id', $household->id)->values());
            }

            return $operation($households);
        });
    }

    public function snapshot(Household $household): string
    {
        return hash('sha256', json_encode([$household->getAttributes(), $household->residents->map->getAttributes()->all()]));
    }

    public function designate(Household $household, Resident $candidate, array $relationships): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'secretary'], true), 403);
        $this->locked([$household->id], function ($households) use ($household, $candidate, $relationships) {
            $household->setRawAttributes($households[$household->id]->getAttributes(), true);
            $this->applyDesignation($household, $candidate, $relationships);
        });
    }

    private function applyDesignation(Household $household, Resident $candidate, array $relationships): void
    {
        Gate::authorize('update', $household);
        $members = $household->residents()->orderBy('id')->lockForUpdate()->get();
        if (! $members->contains('id', $candidate->id)) {
            throw ValidationException::withMessages(['head_reviews' => 'The new household head must belong to this household.']);
        }
        $others = $members->where('id', '!=', $candidate->id);
        foreach (array_keys($relationships) as $id) {
            if ((string) $id !== (string) (int) $id || (int) $id < 1) {
                throw ValidationException::withMessages(['head_reviews' => 'The household member review is invalid.']);
            }
        }
        $expected = $others->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $provided = collect(array_keys($relationships))->map(fn ($id) => (int) $id)->sort()->values()->all();
        if ($expected !== $provided) {
            throw ValidationException::withMessages(['head_reviews' => 'Review every other current household member before confirming.']);
        }
        foreach ($others as $member) {
            HouseholdRelationships::validate($relationships[$member->id], $member->relationship_to_head, 'head_reviews', true);
        }
        $oldHead = $household->head_resident_id;
        foreach ($members as $member) {
            $old = $member->toArray();
            $member->update(['relationship_to_head' => $member->id === $candidate->id ? HouseholdRelationships::HEAD : $relationships[$member->id]]);
            AuditLog::logMutation('updated', auth()->user(), $member, $old, $member->fresh()->toArray());
        }
        $old = $household->toArray();
        $household->update(['head_resident_id' => $candidate->id]);
        $audit = AuditLog::logMutation('updated', auth()->user(), $household, $old, $household->fresh()->toArray());
        $audit->update(['metadata' => ['operation' => 'household_head_designated', 'household_id' => $household->id,
            'old_head_resident_id' => $oldHead, 'new_head_resident_id' => $candidate->id,
            'reviewed_relationships' => $relationships]]);
    }

    public function clearEmpty(Household $household): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'secretary'], true), 403);
        $this->locked([$household->id], function ($households) use ($household) {
            $household->setRawAttributes($households[$household->id]->getAttributes(), true);
            $this->clearLockedEmpty($household);
        });
    }

    private function clearLockedEmpty(Household $household): void
    {
        if ($household->residents()->exists()) {
            throw ValidationException::withMessages(['head_reviews' => 'Choose a replacement head and review the remaining household members.']);
        }
        $old = $household->toArray();
        $household->update(['head_resident_id' => null]);
        AuditLog::logMutation('updated', auth()->user(), $household, $old, $household->fresh()->toArray());
    }
}
