<?php

namespace App\Support;

use App\Models\ProfileUpdateRequest;
use App\Models\Resident;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ResidentLifecycleInventory
{
    public const STATUSES = [Resident::STATUS_ACTIVE, Resident::STATUS_DECEASED,
        Resident::STATUS_RELOCATED, Resident::STATUS_MOVED_OUT];

    private const ID_LIMIT = 100;

    public function report(): array
    {
        $report = ['summary' => ['read_only' => true, 'captured_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'sample_id_limit' => self::ID_LIMIT], 'status_inventory' => [], 'ambiguous' => [],
            'identity' => [], 'ownership' => [], 'corrections' => [], 'blockers' => []];
        try {
            $required = ['residents' => ['id', 'resident_status', 'is_active', 'deleted_at', 'birth_date',
                'date_of_death', 'moved_out_at', 'official_resident_code', 'household_id', 'mobile_uuid', 'philsys_card_no'],
                'households' => ['id', 'purok_id', 'head_resident_id', 'deleted_at'],
                'puroks' => ['id', 'barangay_id', 'deleted_at'], 'barangays' => ['id', 'deleted_at'],
                'profile_update_requests' => ['id', 'subject_type', 'subject_id', 'request_status']];
            foreach ($required as $table => $columns) {
                if (! Schema::hasTable($table) || array_diff($columns, Schema::getColumnListing($table))) {
                    $report['blockers'][] = ['reason' => 'schema_mismatch', 'table' => $table];
                }
            }
            if ($report['blockers']) {
                return $report;
            }
            $columns = Schema::getColumns('residents');
            $report['blockers'] = self::schemaIssues($columns, Schema::getIndexes('residents'), Schema::getForeignKeys('residents'));
            $report['summary']['schema_stage'] = in_array('lifecycle_version', array_column($columns, 'name'), true)
                ? 'l1a_foundation' : 'legacy_compatible';
            $rows = DB::table('residents')->select($required['residents'])->orderBy('id')->get();
            $report['summary'] += ['total_residents' => $rows->count(),
                'non_deleted_residents' => $rows->whereNull('deleted_at')->count(),
                'soft_deleted_residents' => $rows->whereNotNull('deleted_at')->count()];
            foreach (['null_code', 'empty_code', 'nonstandard_code', 'unsupported_status'] as $reason) {
                $report['identity'][$reason] = ['count' => 0, 'resident_ids' => []];
            }
            $today = CarbonImmutable::today(config('app.timezone'));
            foreach ($rows as $row) {
                $key = ($row->resident_status ?? 'NULL').' / '.((bool) $row->is_active ? 'active' : 'inactive')
                    .' / '.($row->deleted_at === null ? 'non_deleted' : 'soft_deleted');
                $report['status_inventory']['combinations'][$key] = ($report['status_inventory']['combinations'][$key] ?? 0) + 1;
                $status = $row->resident_status ?? 'NULL';
                $report['status_inventory']['resident_status'][$status] = ($report['status_inventory']['resident_status'][$status] ?? 0) + 1;
                $active = (bool) $row->is_active ? 'active' : 'inactive';
                $report['status_inventory']['is_active'][$active] = ($report['status_inventory']['is_active'][$active] ?? 0) + 1;
                foreach (self::classifyResident((array) $row, $today) as $reason) {
                    $this->addFinding($report['ambiguous'], $reason, $row->id);
                }
                if (self::statusIssue($row->resident_status)) {
                    $this->addFinding($report['identity'], 'unsupported_status', $row->id);
                }
                if ($reason = self::codeIssue($row->official_resident_code)) {
                    $this->addFinding($report['identity'], $reason, $row->id);
                }
            }
            if ($report['identity']['unsupported_status']['count'] > 0) {
                $report['blockers'][] = ['reason' => 'unsupported_status', ...$report['identity']['unsupported_status']];
            }
            $duplicates = DB::table('residents')->whereNotNull('official_resident_code')
                ->select('official_resident_code')->groupBy('official_resident_code')->havingRaw('COUNT(*) > 1')->get();
            $report['identity']['duplicate_code_groups'] = $duplicates->count();
            foreach ($duplicates as $duplicate) {
                $ids = DB::table('residents')->where('official_resident_code', $duplicate->official_resident_code)->orderBy('id')->pluck('id');
                $report['blockers'][] = ['reason' => 'duplicate_official_resident_code', 'count' => $ids->count(),
                    'resident_ids' => $ids->take(self::ID_LIMIT)->all()];
            }
            $this->inspectOwnership($report);
            if (Schema::hasTable('resident_code_sequences')) {
                $observed = app(ResidentCodeAllocator::class)->observed();
                $sequences = DB::table('resident_code_sequences')->orderBy('origin_barangay_id')->pluck('last_value', 'origin_barangay_id')->all();
                $report['identity']['code_sequences'] = ['malformed_standard_codes' => $observed['malformed_standard_codes'], 'namespaces' => []];
                foreach (array_unique([...array_keys($sequences), ...array_keys($observed['maxima'])]) as $namespace) {
                    $high = isset($sequences[$namespace]) ? (int) $sequences[$namespace] : null;
                    $issued = $observed['maxima'][$namespace] ?? 0;
                    $report['identity']['code_sequences']['namespaces'][] = ['namespace' => (int) $namespace, 'last_value' => $high,
                        'highest_observed_suffix' => $issued, 'behind_observed' => $high === null || $high < $issued];
                    if ($high === null || $high < $issued) {
                        $report['blockers'][] = ['reason' => 'resident_code_sequence_behind_issuance', 'namespace' => (int) $namespace];
                    }
                }
            }
            $report['corrections']['states'] = DB::table('profile_update_requests')
                ->whereIn('subject_type', [ProfileUpdateRequest::SUBJECT_RESIDENT, ProfileUpdateRequest::SUBJECT_HOUSEHOLD])
                ->select('subject_type', 'request_status')->selectRaw('COUNT(*) AS count')
                ->groupBy('subject_type', 'request_status')->get()->all();
            foreach ([ProfileUpdateRequest::SUBJECT_RESIDENT, ProfileUpdateRequest::SUBJECT_HOUSEHOLD] as $type) {
                $query = DB::table('profile_update_requests')->where('subject_type', $type)->where('request_status', ProfileUpdateRequest::STATUS_PENDING);
                $report['corrections']['unresolved'][$type] = ['count' => $query->count(),
                    'request_ids' => (clone $query)->orderBy('id')->limit(self::ID_LIMIT)->pluck('id')->all(),
                    'subject_ids' => (clone $query)->distinct()->orderBy('subject_id')->limit(self::ID_LIMIT)->pluck('subject_id')->all()];
            }
        } catch (Throwable) {
            // Diagnostics must not print database credentials, SQL, or exception payloads.
            $report['blockers'][] = ['reason' => 'inspection_failed', 'detail' => 'Unable to inspect the configured database; check connection and schema availability.'];
        }

        return $report;
    }

    public static function classifyResident(array $row, CarbonImmutable $today): array
    {
        $flags = [];
        $status = $row['resident_status'] ?? null;
        $active = (bool) ($row['is_active'] ?? false);
        if (! empty($row['deleted_at'])) {
            $flags[] = 'soft_deleted';
        }
        if ($status === Resident::STATUS_ACTIVE && ! $active) {
            $flags[] = 'active_status_but_inactive';
        }
        if ($status === Resident::STATUS_RELOCATED) {
            $flags[] = 'legacy_relocated_requires_review';
        }
        if ($active && in_array($status, [Resident::STATUS_DECEASED, Resident::STATUS_RELOCATED, Resident::STATUS_MOVED_OUT], true)) {
            $flags[] = $status.'_but_active';
        }
        $birth = self::date($row['birth_date'] ?? null);
        foreach (['date_of_death' => Resident::STATUS_DECEASED, 'moved_out_at' => Resident::STATUS_MOVED_OUT] as $field => $expected) {
            $value = $row[$field] ?? null;
            $date = self::date($value);
            $valid = $date && $date->format('Y-m-d') <= $today->toDateString()
                && (! $birth || $date >= $birth);
            if ($value && ! $valid) {
                $flags[] = 'invalid_'.$field;
            }
            if ($value && $status === Resident::STATUS_ACTIVE) {
                $flags[] = 'active_with_'.$field;
            }
            if ($status === $expected && ! $valid) {
                $flags[] = $expected.'_without_valid_'.$field;
            }
            if ($value && (($field === 'date_of_death' && in_array($status, [Resident::STATUS_RELOCATED, Resident::STATUS_MOVED_OUT], true))
                || ($field === 'moved_out_at' && $status === Resident::STATUS_DECEASED))) {
                $flags[] = 'mixed_lifecycle_dates';
            }
        }

        return array_values(array_unique($flags));
    }

    public static function codeIssue(?string $code): ?string
    {
        if ($code === null) {
            return 'null_code';
        }
        if (trim($code) === '') {
            return 'empty_code';
        }

        return preg_match('/^RS-[0-9]{4,}-[0-9]{5,}$/D', $code) === 1 ? null : 'nonstandard_code';
    }

    public static function statusIssue(?string $status): ?string
    {
        return in_array($status, self::STATUSES, true) ? null : 'unsupported_status';
    }

    public static function ownershipIssues(array $chain): array
    {
        $issues = [];
        foreach (['household', 'purok', 'barangay'] as $relation) {
            if (($chain[$relation] ?? null) === null) {
                $issues[] = 'missing_'.$relation;
            }
        }

        return $issues;
    }

    public static function schemaIssues(array $columns, array $indexes, array $foreignKeys): array
    {
        $issues = [];
        $byName = array_column($columns, null, 'name');
        $status = $byName['resident_status'] ?? [];
        $types = ["enum('active','deceased','relocated')", "enum('active','deceased','relocated','moved_out')"];
        if (! in_array($status['type'] ?? '', $types, true) || ($status['nullable'] ?? true)
            || trim((string) ($status['default'] ?? ''), "'\"") !== 'active') {
            $issues[] = ['reason' => 'resident_status_schema_mismatch'];
        }
        foreach (['id', 'household_id'] as $name) {
            if (! str_contains($byName[$name]['type'] ?? '', 'bigint') || ! str_contains($byName[$name]['type'] ?? '', 'unsigned')
                || ($byName[$name]['nullable'] ?? true)) {
                $issues[] = ['reason' => 'mandatory_identity_schema_mismatch', 'column' => $name];
            }
        }
        if (isset($byName['lifecycle_version'])) {
            $version = $byName['lifecycle_version'];
            if (! str_contains($version['type'], 'bigint') || ! str_contains($version['type'], 'unsigned')
                || $version['nullable'] || trim((string) $version['default'], "'\"") !== '0') {
                $issues[] = ['reason' => 'lifecycle_version_schema_mismatch'];
            }
            foreach ($indexes as $index) {
                if (in_array('lifecycle_version', $index['columns'], true)) {
                    $issues[] = ['reason' => 'unexpected_lifecycle_version_index'];
                }
            }
        }
        foreach ([['id'], ['official_resident_code'], ['mobile_uuid'], ['philsys_card_no'],
            ['first_name', 'last_name', 'birth_date', 'household_id']] as $expected) {
            if (! array_filter($indexes, fn ($index) => $index['unique'] && $index['columns'] === $expected)) {
                $issues[] = ['reason' => 'missing_unique_constraint', 'columns' => $expected];
            }
        }
        if (! array_filter($indexes, fn ($index) => $index['columns'] === ['resident_status'])) {
            $issues[] = ['reason' => 'missing_resident_status_index'];
        }
        if (! array_filter($foreignKeys, fn ($fk) => $fk['columns'] === ['household_id'] && $fk['foreign_table'] === 'households'
            && $fk['foreign_columns'] === ['id'] && strtolower($fk['on_delete']) === 'cascade')) {
            $issues[] = ['reason' => 'household_foreign_key_mismatch'];
        }

        return $issues;
    }

    private function inspectOwnership(array &$report): void
    {
        $rows = DB::table('residents as r')->leftJoin('households as h', 'h.id', '=', 'r.household_id')
            ->leftJoin('puroks as p', 'p.id', '=', 'h.purok_id')->leftJoin('barangays as b', 'b.id', '=', 'p.barangay_id')
            ->select('r.id', 'h.id as household', 'p.id as purok', 'b.id as barangay',
                'h.deleted_at as household_deleted', 'p.deleted_at as purok_deleted', 'b.deleted_at as barangay_deleted')->orderBy('r.id')->get();
        foreach ($rows as $row) {
            foreach (self::ownershipIssues((array) $row) as $reason) {
                $this->addFinding($report['ownership'], $reason, $row->id);
            }
            if ($row->household_deleted || $row->purok_deleted || $row->barangay_deleted) {
                $this->addFinding($report['ambiguous'], 'archived_ownership_chain', $row->id);
            }
        }
        foreach ($report['ownership'] as $reason => $finding) {
            $report['blockers'][] = ['reason' => $reason, ...$finding];
        }
        $heads = DB::table('households as h')->leftJoin('residents as r', 'r.id', '=', 'h.head_resident_id')
            ->whereNull('h.deleted_at')->select('h.id', 'h.head_resident_id', 'r.id as resident_id', 'r.household_id',
                'r.resident_status', 'r.is_active', 'r.deleted_at')->selectRaw('(SELECT COUNT(*) FROM residents m WHERE m.household_id=h.id AND m.deleted_at IS NULL AND m.resident_status=? AND m.is_active=1) AS legacy_current_members', [Resident::STATUS_ACTIVE])->get();
        $report['ownership']['heads'] = ['households_checked' => $heads->count(), 'headless_with_current_members' => 0,
            'invalid_head_reference' => 0, 'noncurrent_head' => 0, 'head_with_no_current_members' => 0];
        foreach ($heads as $head) {
            foreach (['headless_with_current_members' => ! $head->head_resident_id && $head->legacy_current_members > 0,
                'invalid_head_reference' => $head->head_resident_id && (! $head->resident_id || (int) $head->household_id !== (int) $head->id),
                'noncurrent_head' => $head->head_resident_id && ($head->deleted_at || ! $head->is_active || $head->resident_status !== Resident::STATUS_ACTIVE),
                'head_with_no_current_members' => $head->head_resident_id && ! $head->legacy_current_members] as $reason => $invalid) {
                if ($invalid) {
                    $report['ownership']['heads'][$reason]++;
                    $this->addFinding($report['ambiguous'], $reason, $head->id, 'household_ids');
                }
            }
        }
    }

    private function addFinding(array &$findings, string $reason, int $id, string $ids = 'resident_ids'): void
    {
        $findings[$reason] ??= ['count' => 0, $ids => []];
        $findings[$reason]['count']++;
        if (count($findings[$reason][$ids]) < self::ID_LIMIT) {
            $findings[$reason][$ids][] = $id;
        }
    }

    private static function date(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }
        try {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

            return $date && $date->format('Y-m-d') === $value ? $date : null;
        } catch (Throwable) {
            return null;
        }
    }
}
