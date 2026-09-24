<?php

namespace App\Support;

use Illuminate\Support\Collection;

class ExportDownload
{
    public static function make(
        string $format,
        string $title,
        string $module,
        string $slug,
        array $columns,
        Collection $rows,
        array $filters = [],
        ?string $scope = null,
        ?string $modelType = null,
        ?array $pdfColumns = null,
        string $orientation = 'landscape',
        array $auditMetadata = []
    ) {
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        $generatedAt = now();
        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
        $search = $filters['Search'] ?? null;
        unset($filters['Search']);

        $information = [
            'Report' => $title,
            'Module' => $module,
            'Scope' => $scope ?: 'Authorized records',
            'Generated at' => $generatedAt->format('F j, Y g:i A T'),
            'Search' => $search ?: 'None',
            'Matching records' => $rows->count(),
        ];

        foreach ($filters as $label => $value) {
            $information["Filter: {$label}"] = $value;
        }

        if ($filters === []) {
            $information['Filters'] = 'None';
        }

        $filename = $slug.'_'.$generatedAt->format('Y-m-d_His').'.'.$format;

        $response = match ($format) {
            'csv' => TabularExport::csv($filename, $columns, $rows),
            'xlsx' => TabularExport::xlsx($filename, 'Data', $columns, $rows, $information),
            'pdf' => TabularExport::pdf($filename, $title, $pdfColumns ?? $columns, $rows, [], $information, 'a4', $orientation),
        };

        ExportAudit::log($title, $format, array_merge($auditMetadata, [
            'model_type' => $modelType,
            'record_count' => $rows->count(),
            'filters' => array_filter(['Search' => $search] + $filters),
        ]));

        return $response;
    }
}
