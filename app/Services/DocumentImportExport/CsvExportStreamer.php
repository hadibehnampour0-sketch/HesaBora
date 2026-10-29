<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Clean-Room Streaming CSV Exporter for Accounting Documents.
 *
 * Handles HTTP response streaming, UTF-8 BOM injection, and column selection.
 *
 * License: MIT
 */
class CsvExportStreamer
{
    public function __construct(
        protected readonly DocumentExportRowTransformer $rowTransformer = new DocumentExportRowTransformer
    ) {}

    public function generateExportResponse(Builder $query, array $filterParameters): StreamedResponse
    {
        $targetFilename = __('Documents report').'-'.formatDate(Carbon::now(), 'Y-m-d').'.csv';
        $exportColumns = $this->determineSelectedColumns($filterParameters);

        return $this->streamCsvData($query, $targetFilename, $exportColumns);
    }

    public function determineSelectedColumns(array $filters): array
    {
        $requiredKeys = AccountingFileSchema::MANDATORY_COLUMNS;
        $optionalKeys = array_values(array_diff(AccountingFileSchema::ALL_COLUMNS, $requiredKeys));

        $requestedOptional = ! empty($filters['columns_selected'])
            ? array_values(array_intersect($optionalKeys, (array) ($filters['columns'] ?? [])))
            : $optionalKeys;

        return array_values(array_filter(
            AccountingFileSchema::ALL_COLUMNS,
            static fn (string $col): bool => in_array($col, $requiredKeys, true) || in_array($col, $requestedOptional, true)
        ));
    }

    protected function streamCsvData(Builder $query, string $fileName, array $columns): StreamedResponse
    {
        $rtlOrderedColumns = array_reverse($columns);

        $httpHeaders = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="documents_export.csv"; filename*=UTF-8\'\''.rawurlencode($fileName),
        ];

        return response()->stream(function () use ($query, $rtlOrderedColumns): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            $localizedHeaders = array_map(static fn (string $col): string => __($col), $rtlOrderedColumns);
            fputcsv($handle, $localizedHeaders);

            (clone $query)
                ->with(['transactions.subject'])
                ->orderBy('date')
                ->orderBy('number')
                ->chunk(200, function ($batch) use ($handle, $rtlOrderedColumns): void {
                    /** @var Document $docItem */
                    foreach ($batch as $docItem) {
                        foreach ($this->rowTransformer->extractRowsForDocument($docItem, $rtlOrderedColumns) as $csvLine) {
                            fputcsv($handle, $csvLine);
                        }
                    }
                });

            fclose($handle);
        }, 200, $httpHeaders);
    }
}
