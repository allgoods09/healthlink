<?php

namespace App\Support\Lifecycle;

use App\Models\Resident;
use App\Models\ResidentLifecycleEvent;
use App\Support\ResidentLifecycleInventory;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;

class RegistryCaptureBootstrap
{
    public const VERSION = 1;

    public const DEFAULT_BATCH_SIZE = 250;

    private const COLUMNS = ['id', 'household_id', 'resident_status', 'is_active', 'deleted_at',
        'birth_date', 'created_at', 'updated_at', 'moved_in_at', 'moved_out_at', 'date_of_death'];

    public function __construct(private LifecycleEventRecorder $recorder) {}

    public function inventory(?int $throughId = null, int $batchSize = self::DEFAULT_BATCH_SIZE): array
    {
        $this->validateBounds($throughId, $batchSize);
        $report = ['through_id' => null, 'cutoff_source' => null, 'batch_size' => $batchSize,
            'total_eligible' => 0, 'already_captured' => 0, 'would_create' => 0, 'estimated_batch_count' => 0,
            'ambiguous_legacy_classifications' => [], 'ownership_problems' => [], 'missing_context' => 0,
            'soft_deleted_context' => 0, 'blockers' => $this->schemaProblems()];
        if ($report['blockers']) {
            return $report;
        }
        $retained = $this->retainedCutoff();
        $maximum = (int) DB::table('residents')->max('id');
        if ($throughId !== null && $throughId > $maximum && $throughId !== $retained) {
            throw new InvalidArgumentException('through-id cannot exceed the current maximum Resident ID unless it is the retained capture cutoff.');
        }
        $cutoff = $throughId ?? $retained ?? $maximum;
        $report['through_id'] = $cutoff;
        $report['cutoff_source'] = $throughId !== null ? 'explicit' : ($retained !== null ? 'retained_capture' : 'current_maximum');
        $coverage = $this->coverage($cutoff);
        $report['total_eligible'] = $coverage['total_residents'];
        $report['already_captured'] = $coverage['with_registry_capture'];
        $report['would_create'] = $coverage['missing_registry_capture'];
        $report['estimated_batch_count'] = (int) ceil($report['would_create'] / $batchSize);
        $report['blockers'] = $coverage['blockers'];
        $today = CarbonImmutable::today(config('app.timezone'));
        $this->residents($cutoff)->chunkById($batchSize, function ($residents) use (&$report, $today): void {
            foreach ($residents as $resident) {
                foreach (ResidentLifecycleInventory::classifyResident($resident->getAttributes(), $today) as $reason) {
                    $report['ambiguous_legacy_classifications'][$reason] = ($report['ambiguous_legacy_classifications'][$reason] ?? 0) + 1;
                }
                if (ResidentLifecycleInventory::statusIssue($resident->resident_status)) {
                    $report['ambiguous_legacy_classifications']['unsupported_status'] = ($report['ambiguous_legacy_classifications']['unsupported_status'] ?? 0) + 1;
                }
                [$context, $missing, $archived] = $this->context($resident);
                foreach ($missing as $reason) {
                    $report['ownership_problems'][$reason] = ($report['ownership_problems'][$reason] ?? 0) + 1;
                }
                $report['missing_context'] += (int) ($missing !== []);
                $report['soft_deleted_context'] += (int) ($archived !== []);
            }
        });

        return $report;
    }

    public function apply(?int $throughId = null, int $batchSize = self::DEFAULT_BATCH_SIZE, ?Closure $afterBatch = null): array
    {
        $report = $this->inventory($throughId, $batchSize);
        if ($report['blockers']) {
            throw new LogicException('Registry capture has blocking schema or baseline-history problems.');
        }
        $cutoff = $report['through_id'];
        $result = ['through_id' => $cutoff, 'created' => 0, 'batches_completed' => 0, 'last_committed_id' => 0];
        $connection = DB::connection();
        while (true) {
            $batch = $connection->transaction(function () use ($cutoff, $batchSize, $result): array {
                $residents = $this->residents($cutoff)->where('id', '>', $result['last_committed_id'])
                    ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('resident_lifecycle_events')
                        ->whereColumn('resident_id', 'residents.id')->where('event_key', ResidentLifecycleEvent::REGISTRY_CAPTURE))
                    ->orderBy('id')->limit($batchSize)->lockForUpdate()->get();
                $created = 0;
                foreach ($residents as $resident) {
                    // Recheck under the Resident lock: a competing bootstrap may have captured it while we waited.
                    $existing = ResidentLifecycleEvent::where('resident_id', $resident->id)
                        ->where('event_key', ResidentLifecycleEvent::REGISTRY_CAPTURE)->lockForUpdate()->first();
                    if ($existing) {
                        if (! $this->validBaseline($existing)) {
                            throw new LogicException('The registry capture key is occupied by incompatible facts.');
                        }

                        continue;
                    }
                    [$context, $missing, $archived] = $this->context($resident);
                    $dates = [];
                    foreach (['created_at', 'updated_at', 'deleted_at', 'moved_in_at', 'moved_out_at', 'date_of_death'] as $field) {
                        $dates[$field] = $resident->getRawOriginal($field);
                    }
                    $this->recorder->record($resident, ResidentLifecycleEvent::REGISTRY_CAPTURE, ResidentLifecycleEvent::REGISTRY_CAPTURE,
                        source: $context, remarks: 'Existing Registry Record Captured',
                        metadata: ['capture_schema_version' => self::VERSION, 'context_semantics' => 'observed_at_capture',
                            'bootstrap' => ['through_id' => $cutoff],
                            'observed' => ['resident_status' => $resident->resident_status, 'is_active' => $resident->is_active,
                                'soft_deleted' => $resident->trashed(), 'legacy_dates' => $dates,
                                'missing_context' => $missing, 'soft_deleted_context' => $archived]],
                        provenance: ResidentLifecycleEvent::REGISTRY_CAPTURE);
                    $created++;
                }

                return ['count' => $residents->count(), 'created' => $created, 'last_id' => $residents->last()?->id];
            }, 5);
            if ($batch['count'] === 0) {
                break;
            }
            $result['created'] += $batch['created'];
            $result['batches_completed']++;
            $result['last_committed_id'] = $batch['last_id'];
            $afterBatch?->__invoke($result);
        }

        return $result;
    }

    public function coverage(?int $throughId = null): array
    {
        if (! Schema::hasTable('resident_lifecycle_events')) {
            return ['available' => false, 'blockers' => []];
        }
        $residents = DB::table('residents')->when($throughId !== null, fn ($q) => $q->where('id', '<=', $throughId));
        $events = DB::table('resident_lifecycle_events')->when($throughId !== null, fn ($q) => $q->where('resident_id', '<=', $throughId));
        $captured = (clone $residents)->whereExists(fn ($q) => $q->selectRaw('1')->from('resident_lifecycle_events')
            ->whereColumn('resident_id', 'residents.id')->where('event_type', ResidentLifecycleEvent::REGISTRY_CAPTURE)
            ->where('event_key', ResidentLifecycleEvent::REGISTRY_CAPTURE))->count();
        $duplicates = (clone $events)->where('event_type', ResidentLifecycleEvent::REGISTRY_CAPTURE)
            ->select('resident_id')->groupBy('resident_id')->havingRaw('COUNT(*) > 1')->get()->count();
        $invalid = (clone $events)->where(fn ($q) => $q->where('event_type', ResidentLifecycleEvent::REGISTRY_CAPTURE)
            ->orWhere('event_key', ResidentLifecycleEvent::REGISTRY_CAPTURE))
            ->where(function ($q): void {
                $q->where('event_type', '!=', ResidentLifecycleEvent::REGISTRY_CAPTURE)
                    ->orWhere('event_key', '!=', ResidentLifecycleEvent::REGISTRY_CAPTURE)
                    ->orWhereNotNull('effective_date')->orWhereNotNull('actor_user_id')
                    ->orWhereNotNull('actor_name_snapshot')->orWhereNotNull('actor_role_snapshot')
                    ->orWhereNull('provenance')->orWhere('provenance', '!=', ResidentLifecycleEvent::REGISTRY_CAPTURE);
            })->count();
        $blockers = [];
        if ($duplicates > 0) {
            $blockers[] = ['reason' => 'duplicate_registry_capture', 'count' => $duplicates];
        }
        if ($invalid > 0) {
            $blockers[] = ['reason' => 'incompatible_registry_capture', 'count' => $invalid];
        }
        $total = $residents->count();

        return ['available' => true, 'total_residents' => $total, 'with_registry_capture' => $captured,
            'missing_registry_capture' => $total - $captured, 'duplicate_baseline_groups' => $duplicates,
            'incompatible_baselines' => $invalid, 'other_event_counts' => (clone $events)
                ->where('event_type', '!=', ResidentLifecycleEvent::REGISTRY_CAPTURE)->select('event_type')
                ->selectRaw('COUNT(*) AS count')->groupBy('event_type')->orderBy('event_type')->pluck('count', 'event_type')->all(),
            'blockers' => $blockers];
    }

    private function residents(int $cutoff): Builder
    {
        return Resident::withTrashed()->select(self::COLUMNS)->where('id', '<=', $cutoff)->with([
            'household' => fn ($q) => $q->withTrashed(),
            'household.purok' => fn ($q) => $q->withTrashed(),
            'household.purok.barangay' => fn ($q) => $q->withTrashed(),
        ]);
    }

    private function context(Resident $resident): array
    {
        $context = ['household' => $resident->household, 'purok' => $resident->household?->purok,
            'barangay' => $resident->household?->purok?->barangay];
        $missing = ResidentLifecycleInventory::ownershipIssues($context);
        $archived = array_keys(array_filter($context, fn ($model) => $model?->trashed()));

        return [$context, $missing, $archived];
    }

    private function retainedCutoff(): ?int
    {
        $metadata = DB::table('resident_lifecycle_events')->where('event_type', ResidentLifecycleEvent::REGISTRY_CAPTURE)
            ->where('event_key', ResidentLifecycleEvent::REGISTRY_CAPTURE)->where('provenance', ResidentLifecycleEvent::REGISTRY_CAPTURE)
            ->where('metadata->capture_schema_version', self::VERSION)->whereNotNull('metadata->bootstrap->through_id')
            ->orderBy('id')->value('metadata');
        if ($metadata === null) {
            return null;
        }
        $cutoff = data_get(json_decode($metadata, true, flags: JSON_THROW_ON_ERROR), 'bootstrap.through_id');
        if (! is_int($cutoff) || $cutoff < 0) {
            throw new LogicException('Invalid retained registry capture cutoff; inspect baseline provenance before resuming.');
        }

        return $cutoff;
    }

    private function validBaseline(ResidentLifecycleEvent $event): bool
    {
        return $event->event_type === ResidentLifecycleEvent::REGISTRY_CAPTURE && $event->effective_date === null
            && $event->actor_user_id === null && $event->actor_name_snapshot === null && $event->actor_role_snapshot === null
            && $event->provenance === ResidentLifecycleEvent::REGISTRY_CAPTURE;
    }

    private function validateBounds(?int $throughId, int $batchSize): void
    {
        if (($throughId !== null && $throughId < 0) || $batchSize < 1 || $batchSize > 1000) {
            throw new InvalidArgumentException('through-id must be nonnegative; batch-size must be between 1 and 1000.');
        }
    }

    private function schemaProblems(): array
    {
        $events = ['id', 'resident_id', 'event_type', 'event_key', 'effective_date', 'recorded_at', 'actor_user_id',
            'actor_name_snapshot', 'actor_role_snapshot', 'remarks', 'metadata', 'provenance'];
        foreach (['source', 'destination'] as $side) {
            foreach (['barangay_id', 'purok_id', 'household_id', 'barangay_name_snapshot', 'purok_number_snapshot', 'purok_name_snapshot', 'household_no_snapshot'] as $field) {
                $events[] = $side.'_'.$field;
            }
        }
        $required = ['residents' => self::COLUMNS, 'resident_lifecycle_events' => $events,
            'households' => ['id', 'purok_id', 'household_no', 'deleted_at'],
            'puroks' => ['id', 'barangay_id', 'purok_number', 'purok_name', 'deleted_at'],
            'barangays' => ['id', 'name', 'deleted_at']];
        $problems = [];
        foreach ($required as $table => $columns) {
            if (! Schema::hasTable($table) || array_diff($columns, Schema::getColumnListing($table))) {
                $problems[] = ['reason' => 'schema_mismatch', 'table' => $table];
            }
        }
        if (! $problems && ! array_filter(Schema::getIndexes('resident_lifecycle_events'),
            fn ($index) => $index['unique'] && $index['columns'] === ['resident_id', 'event_key'])) {
            $problems[] = ['reason' => 'missing_lifecycle_event_key_constraint'];
        }

        return $problems;
    }
}
