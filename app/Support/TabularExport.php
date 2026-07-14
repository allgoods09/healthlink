<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TabularExport
{
    /**
     * Build a CSV download for a tabular dataset.
     */
    public static function csv(string $filename, array $columns, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns, $rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, array_keys($columns));

            foreach ($rows as $row) {
                fputcsv($handle, self::mapRow($row, $columns));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Build an XLSX download for a tabular dataset.
     */
    public static function xlsx(string $filename, string $sheetName, array $columns, iterable $rows): Response
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($sheetName, 0, 31));

        $headers = array_keys($columns);

        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $rowNumber = 2;

        foreach ($rows as $row) {
            foreach (self::mapRow($row, $columns) as $index => $value) {
                $sheet->setCellValueByColumnAndRow($index + 1, $rowNumber, (string) $value);
            }

            $rowNumber++;
        }

        foreach (range(1, count($headers)) as $index) {
            $sheet->getColumnDimensionByColumn($index)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'healthlink-export-');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Build a PDF download for a tabular dataset.
     */
    public static function pdf(
        string $filename,
        string $title,
        array $columns,
        iterable $rows,
        array $filters = []
    ): Response {
        $mappedRows = [];

        foreach ($rows as $row) {
            $mappedRows[] = self::mapRow($row, $columns);
        }

        $pdf = Pdf::loadView('exports.table', [
            'title' => $title,
            'headers' => array_keys($columns),
            'rows' => $mappedRows,
            'filters' => array_filter($filters),
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    /**
     * Convert a row object into ordered export values.
     */
    private static function mapRow(mixed $row, array $columns): array
    {
        return array_map(function (mixed $definition) use ($row) {
            if ($definition instanceof Closure) {
                return $definition($row);
            }

            if (is_string($definition)) {
                return data_get($row, $definition);
            }

            return $definition;
        }, array_values($columns));
    }
}
