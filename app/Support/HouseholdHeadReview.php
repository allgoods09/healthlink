<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Resident;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HouseholdHeadReview
{
    public function __construct(private HouseholdHeadManager $heads) {}

    public function run(Request $request, array $householdIds, array $plans, callable $save): mixed
    {
        try {
            return $this->reviewLocked($request, $householdIds, $plans, $save);
        } catch (ValidationException $exception) {
            $exception->redirectTo($this->formUrl($request));

            throw $exception;
        }
    }

    private function reviewLocked(Request $request, array $householdIds, array $plans, callable $save): mixed
    {
        return $this->heads->locked($householdIds, function ($households) use ($request, $plans, $save) {
            $payload = Arr::except($request->all(), ['_token', '_method', 'head_review_token', 'head_reviews']);
            $binding = hash('sha256', json_encode([$request->user()->id, $request->path(), $request->method(), $this->normalized($payload), $plans]));
            $snapshots = $households->map(fn ($h) => $this->heads->snapshot($h))->all();
            $needsReview = collect($plans)->contains(function ($plan, $id) use ($households) {
                return $households[$id]->residents->where('id', '!=', $plan['exclude_id'] ?? 0)
                    ->where('id', '!=', $plan['candidate_id'] ?? 0)->isNotEmpty();
            }) || collect($plans)->contains(fn ($plan) => $plan['choose_candidate'] ?? false);
            $token = $request->input('head_review_token');
            if ($token) {
                $review = is_string($token) ? $request->session()->get('household_head_reviews.'.$token) : null;
                if (! $review || $review['binding'] !== $binding || $review['snapshots'] !== $snapshots || $review['expires'] < time()) {
                    throw ValidationException::withMessages(['head_reviews' => 'This household changed or the review expired. Return to the form and review it again.']);
                }
            } elseif ($needsReview) {
                $token = (string) Str::uuid();
                $request->session()->put('household_head_reviews.'.$token, ['binding' => $binding, 'snapshots' => $snapshots, 'expires' => time() + 1800]);

                return view('households.head-review', ['plans' => $plans, 'households' => $households,
                    'payload' => $payload, 'token' => $token, 'action' => $request->url(), 'method' => $request->method(),
                    'layout' => $request->user()->role === 'admin' ? 'layouts.admin' : 'layouts.portal',
                    'cancelUrl' => $this->formUrl($request)]);
            }
            $reviews = $request->input('head_reviews', []);
            if (! is_array($reviews) || array_diff(array_keys($reviews), array_keys($plans))) {
                throw ValidationException::withMessages(['head_reviews' => 'The household review is invalid.']);
            }
            foreach ($plans as $id => &$plan) {
                if ($plan['choose_candidate'] ?? false) {
                    $plan['candidate_id'] = filter_var(data_get($reviews, $id.'.candidate_id'), FILTER_VALIDATE_INT);
                    if (! $plan['candidate_id'] || ! $households[$id]->residents->where('id', '!=', $plan['exclude_id'])->contains('id', $plan['candidate_id'])) {
                        throw ValidationException::withMessages(['head_reviews' => 'Choose a replacement head from the remaining members.']);
                    }
                }
                $plan['relationships'] = data_get($reviews, $id.'.relationships', []);
                if (! is_array($plan['relationships'])) {
                    throw ValidationException::withMessages(['head_reviews' => 'Review the household member relationships.']);
                }
                // A source replacement selector renders all remaining members; the chosen head is system-controlled.
                if ($plan['choose_candidate'] ?? false) {
                    unset($plan['relationships'][$plan['candidate_id']]);
                }
            }
            unset($plan);
            $result = $save($households, $plans, $this->heads);
            if ($token) {
                $request->session()->forget('household_head_reviews.'.$token);
            }

            return $result;
        });
    }

    public function resident(Request $request, array $data, ?Resident $resident, callable $save, bool $compatibility = false): mixed
    {
        $targetId = (int) $data['household_id'];
        $source = $resident?->household;
        $sourceId = $source?->id;
        $moving = $resident && $targetId !== (int) $resident->household_id;
        $retaining = $resident && ! $moving && $resident->is_household_head;
        $designating = $request->boolean('set_as_household_head') && ! $retaining;
        $plans = [];
        if ($designating) {
            $plans[$targetId] = ['candidate_id' => $resident?->id, 'candidate_name' => trim($data['first_name'].' '.$data['last_name'])];
        }
        if ($moving && $resident->is_household_head && $source->residents()->whereKeyNot($resident->id)->exists()) {
            $plans[$source->id] = ['choose_candidate' => true, 'exclude_id' => $resident->id];
        }
        if ($designating || $retaining) {
            $data['relationship_to_head'] = HouseholdRelationships::HEAD;
        } elseif ($compatibility && HouseholdRelationships::isHead($data['relationship_to_head'])) {
            throw ValidationException::withMessages(['relationship_to_head' => 'Resolve this member\'s ordinary relationship, or explicitly designate them as head and review the household.']);
        } elseif ($request->user()->role === 'secretary' && ! $compatibility) {
            HouseholdRelationships::validate($data['relationship_to_head'], $moving && $resident->is_household_head ? null : $resident?->relationship_to_head);
        } elseif (HouseholdRelationships::isHead($data['relationship_to_head']) && $data['relationship_to_head'] !== $resident?->relationship_to_head) {
            throw ValidationException::withMessages(['relationship_to_head' => 'Use an explicit household head selection, not relationship text.']);
        }

        return $this->run($request, array_values(array_unique(array_filter([$targetId, $source?->id]))), $plans,
            function ($households, $plans, $heads) use ($save, $data, $resident, $moving, $retaining, $targetId, $sourceId) {
                $lockedResident = $resident ? $households[$sourceId]->residents->firstWhere('id', $resident->id) : null;
                if ($resident && ! $lockedResident) {
                    throw ValidationException::withMessages(['head_reviews' => 'This resident moved. Reload the form before saving.']);
                }
                if ($moving && (int) $households[$sourceId]->head_resident_id === (int) $resident->id
                    && ! isset($plans[$targetId]) && HouseholdRelationships::isHead($data['relationship_to_head'])) {
                    throw ValidationException::withMessages(['relationship_to_head' => 'Choose an ordinary relationship in the destination household, or explicitly designate this resident as its head.']);
                }
                if ($lockedResident && ! $moving && (int) $households[$targetId]->head_resident_id === (int) $resident->id) {
                    $data['relationship_to_head'] = HouseholdRelationships::HEAD;
                } elseif ($retaining) {
                    throw ValidationException::withMessages(['head_reviews' => 'The household head changed. Reload the form.']);
                }
                $saved = $save($data, $lockedResident);
                foreach ($plans as $id => $plan) {
                    $candidate = ($plan['choose_candidate'] ?? false)
                        ? Resident::findOrFail($plan['candidate_id']) : $saved;
                    $heads->designate($households[$id], $candidate, $plan['relationships']);
                }
                if ($moving && (int) $households[$sourceId]->head_resident_id === (int) $resident->id) {
                    $heads->clearEmpty($households[$sourceId]);
                }

                return $saved;
            });
    }

    public function household(Request $request, array $data, Household $household, callable $save): mixed
    {
        $head = array_key_exists('head_resident_id', $data) ? $data['head_resident_id'] : $household->head_resident_id;
        if ($household->head_resident_id && ! $head) {
            throw ValidationException::withMessages(['head_resident_id' => 'Select a replacement household head. The current head cannot be removed here.']);
        }
        $plans = $head && (int) $head !== (int) $household->head_resident_id
            ? [$household->id => ['candidate_id' => (int) $head, 'candidate_name' => Resident::find($head)?->full_name]] : [];

        return $this->run($request, [$household->id], $plans, function ($households, $plans, $heads) use ($data, $household, $save) {
            $saved = $save(Arr::except($data, ['head_resident_id']), $households[$household->id]);
            foreach ($plans as $id => $plan) {
                $heads->designate($saved, Resident::findOrFail($plan['candidate_id']), $plan['relationships']);
            }
            if ($saved->head_resident_id) {
                $member = $saved->residents()->findOrFail($saved->head_resident_id);
                if ($member->relationship_to_head !== HouseholdRelationships::HEAD) {
                    $old = $member->toArray();
                    $member->update(['relationship_to_head' => HouseholdRelationships::HEAD]);
                    AuditLog::logMutation('updated', auth()->user(), $member, $old, $member->fresh()->toArray());
                }
            }

            return $saved;
        });
    }

    private function normalized(array $values): array
    {
        ksort($values);
        foreach ($values as &$value) {
            $value = is_array($value) ? $this->normalized($value) : (string) $value;
        }

        return $values;
    }

    private function formUrl(Request $request): string
    {
        $name = $request->route()?->getName() ?? '';
        $form = preg_replace('/\.(store|update|approve)$/', match (true) {
            str_ends_with($name, '.store') => '.create',
            default => '.edit',
        }, $name);

        return Route::has($form) ? route($form, $request->route()->parameters())
            : route($request->user()->role.'.households.index');
    }
}
