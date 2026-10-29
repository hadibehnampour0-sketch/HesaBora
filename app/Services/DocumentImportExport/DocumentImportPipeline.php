<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Clean-Room Document Import Execution Pipeline.
 *
 * Parses tabular files, validates format conformance, and coordinates transactional insertion.
 *
 * License: MIT
 */
class DocumentImportPipeline
{
    public function __construct(
        protected readonly DocumentImportFormatRegistry $registry
    ) {}

    public function execute(UploadedFile $sourceFile, User $operator, string $formatIdentifier): array
    {
        $selectedDriver = $this->registry->get($formatIdentifier);
        [$detectedHeaders, $tabularRows, $skippedMalformedCount] = $this->extractRowsFromFile($sourceFile);

        if (! $selectedDriver->matches($detectedHeaders)) {
            throw ValidationException::withMessages([
                'file' => __('The uploaded file does not match the selected ":format" format.', [
                    'format' => $selectedDriver->label(),
                ]),
            ]);
        }

        /** @var array $executionMetrics */
        $executionMetrics = DB::transaction(static fn () => $selectedDriver->import($tabularRows, $operator));
        $executionMetrics['rows_skipped'] = ($executionMetrics['rows_skipped'] ?? 0) + $skippedMalformedCount;

        return $executionMetrics;
    }

    protected function extractRowsFromFile(UploadedFile $file): array
    {
        $resource = fopen($file->getRealPath(), 'r');
        if ($resource === false) {
            return [[], [], 0];
        }

        $preamble = fread($resource, 3);
        if ($preamble !== chr(0xEF).chr(0xBB).chr(0xBF)) {
            rewind($resource);
        }

        $headerRow = null;
        $rowCollection = [];
        $unalignedCount = 0;

        while (($tokens = fgetcsv($resource)) !== false) {
            if ($headerRow === null) {
                $headerRow = array_map(static fn (mixed $cell): string => trim((string) $cell), $tokens);

                continue;
            }

            if (count($tokens) !== count($headerRow)) {
                $unalignedCount++;

                continue;
            }

            $rowCollection[] = array_combine($headerRow, $tokens);
        }

        fclose($resource);

        return [$headerRow ?? [], $rowCollection, $unalignedCount];
    }
}
