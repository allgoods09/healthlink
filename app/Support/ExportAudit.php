<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class ExportAudit
{
    /**
     * Record an export action in the audit trail.
     */
    public static function log(string $resource, string $format, array $metadata = []): void
    {
        $baseMetadata = array_filter([
            'resource' => $resource,
            'format' => $format,
            'record_count' => $metadata['record_count'] ?? null,
            'record_ids' => $metadata['record_ids'] ?? null,
            'filters' => $metadata['filters'] ?? null,
        ], fn (mixed $value) => ! is_null($value));

        AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'exported',
            'event_description' => 'Exported '.$resource.' as '.strtoupper($format),
            'model_type' => $metadata['model_type'] ?? null,
            'metadata' => array_merge(
                $baseMetadata,
                Arr::except($metadata, ['model_type', 'record_count', 'record_ids', 'filters'])
            ),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
