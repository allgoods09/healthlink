<?php

namespace App\Support;

use App\Models\Household;
use App\Models\Resident;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class ResidentCodeAllocator
{
    public function saveResident(Resident $resident, Closure $save): bool
    {
        if ($resident->exists) {
            if ($resident->isDirty('official_resident_code')) {
                $this->fail('An existing Resident code cannot be changed.');
            }

            return $save();
        }
        $attributes = $resident->getAttributes();
        $original = $resident->getRawOriginal();
        $reset = function () use ($resident, $attributes, $original): void {
            $resident->setRawAttributes($original, true);
            $resident->setRawAttributes($attributes);
            $resident->exists = false;
            $resident->wasRecentlyCreated = false;
        };
        try {
            $saved = $resident->getConnection()->transaction(function () use ($resident, $save, $reset): bool {
                // Deadlock retries must start with the original unsaved model, not a rolled-back insert.
                $reset();
                $code = $resident->official_resident_code;
                if ($code !== null && $code !== '') {
                    if ($parts = self::parse($code)) {
                        $this->reserve($resident->getConnection(), $parts['namespace'], $parts['value']);
                    } elseif (str_starts_with(strtoupper($code), 'RS-')) {
                        $this->fail('Malformed or unsupported standard Resident code.');
                    }
                } else {
                    $household = Household::on($resident->getConnectionName())->with('purok.barangay')->find($resident->household_id);
                    if (! $household?->purok?->barangay) {
                        $this->fail('A valid household, purok and issuance barangay are required.');
                    }
                    $namespace = (int) $household->purok->barangay_id;
                    $sequence = $this->lockedSequence($resident->getConnection(), $namespace);
                    if ($sequence >= PHP_INT_MAX) {
                        $this->fail('Resident code sequence is exhausted.');
                    }
                    $value = $sequence + 1;
                    $code = sprintf('RS-%04d-%05d', $namespace, $value);
                    if (strlen($code) > 30) {
                        $this->fail('Resident code exceeds the supported identifier length.');
                    }
                    $resident->getConnection()->table('resident_code_sequences')->where('origin_barangay_id', $namespace)->update(['last_value' => $value]);
                    $resident->setAttribute('official_resident_code', $code);
                }

                return $save();
            }, 5);
            if (! $saved) {
                $reset();
            }

            return $saved;
        } catch (Throwable $exception) {
            $reset();
            throw $exception;
        }
    }

    /** Positive numeric RS namespace/suffix; shorter explicit suffix padding is preserved, never reformatted. */
    public static function parse(?string $code): ?array
    {
        if ($code === null || strlen($code) > 30 || ! preg_match('/^RS-([0-9]{4,})-([0-9]+)$/D', $code, $matches)) {
            return null;
        }
        $numbers = [];
        foreach ([$matches[1], $matches[2]] as $value) {
            $digits = ltrim($value, '0');
            if ($digits === '' || strlen($digits) > strlen((string) PHP_INT_MAX)
                || (strlen($digits) === strlen((string) PHP_INT_MAX) && strcmp($digits, (string) PHP_INT_MAX) > 0)) {
                return null;
            }
            $numbers[] = (int) $digits;
        }

        return ['namespace' => $numbers[0], 'value' => $numbers[1]];
    }

    public function observed(?Connection $connection = null): array
    {
        $connection ??= DB::connection();
        $maxima = [];
        $malformed = 0;
        $consume = function (?string $code) use (&$maxima, &$malformed): void {
            if ($parts = self::parse($code)) {
                $maxima[$parts['namespace']] = max($maxima[$parts['namespace']] ?? 0, $parts['value']);
            } elseif ($code !== null && str_starts_with(strtoupper($code), 'RS-')) {
                $malformed++;
            }
        };
        // Physical rows include soft-deleted residents; neither current ownership nor status is consulted.
        foreach ($connection->table('residents')->whereNotNull('official_resident_code')->orderBy('id')->cursor(['official_resident_code']) as $row) {
            $consume($row->official_resident_code);
        }
        if (Schema::connection($connection->getName())->hasTable('archived_records')) {
            $archives = $connection->table('archived_records')->where('original_table', 'residents')->orderBy('id')
                ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(data_snapshot, '$.official_resident_code')) AS code")->cursor();
            foreach ($archives as $archive) {
                $consume($archive->code);
            }
        }
        ksort($maxima);

        return ['maxima' => $maxima, 'malformed_standard_codes' => $malformed];
    }

    public function initialize(?Connection $connection = null): array
    {
        $connection ??= DB::connection();
        $observed = $this->observed($connection);
        foreach ($observed['maxima'] as $namespace => $value) {
            $connection->transaction(fn () => $this->reserve($connection, (int) $namespace, $value), 5);
        }

        return $observed;
    }

    private function reserve(Connection $connection, int $namespace, int $value): void
    {
        $current = $this->lockedSequence($connection, $namespace);
        if ($value > $current) {
            $connection->table('resident_code_sequences')->where('origin_barangay_id', $namespace)->update(['last_value' => $value]);
        }
    }

    private function lockedSequence(Connection $connection, int $namespace): int
    {
        if ($namespace < 1) {
            $this->fail('Invalid Resident issuance namespace.');
        }
        // A no-op upsert takes an exclusive row lock immediately, avoiding INSERT IGNORE's shared-lock upgrade deadlocks.
        $connection->table('resident_code_sequences')->upsert(
            [['origin_barangay_id' => $namespace, 'last_value' => 0]],
            ['origin_barangay_id'], ['origin_barangay_id']
        );
        $current = (int) $connection->table('resident_code_sequences')->where('origin_barangay_id', $namespace)->lockForUpdate()->value('last_value');
        // First use is safe even before an explicit initialization run; the row lock serializes competing creators.
        if ($current === 0) {
            $observed = $this->observed($connection)['maxima'][$namespace] ?? 0;
            if ($observed > 0) {
                $connection->table('resident_code_sequences')->where('origin_barangay_id', $namespace)->update(['last_value' => $observed]);
                $current = $observed;
            }
        }

        return $current;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['official_resident_code' => $message]);
    }
}
