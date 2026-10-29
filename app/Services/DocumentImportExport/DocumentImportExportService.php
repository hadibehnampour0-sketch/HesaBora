<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Clean-Room Implementation: Accounting Document Import & Export Service Orchestrator.
 *
 * Coordinates CSV file parsing, streaming export generation with UTF-8 BOM,
 * filter query compilation, and transactional import orchestration.
 *
 * License: MIT
 */
class DocumentImportExportService
{
    public const ALL_COLUMNS = AccountingFileSchema::ALL_COLUMNS;

    public const MANDATORY_COLUMNS = AccountingFileSchema::MANDATORY_COLUMNS;

    public function __construct(
        protected readonly DocumentImportFormatRegistry $formats = new DocumentImportFormatRegistry,
        protected readonly DocumentFilterQueryBuilder $queryBuilder = new DocumentFilterQueryBuilder,
        protected readonly CsvExportStreamer $exportStreamer = new CsvExportStreamer,
        protected readonly ImportFeedbackFormatter $feedbackFormatter = new ImportFeedbackFormatter
    ) {}

    public function buildQuery(array $filters): Builder
    {
        return $this->queryBuilder->compile($filters);
    }

    public function validateExportRequest(Request $request): array
    {
        return (new DocumentImportExportValidator($this->formats))->validateExportPayload($request);
    }

    public function validateImportRequest(Request $request): void
    {
        (new DocumentImportExportValidator($this->formats))->validateImportPayload($request);
    }

    public function export(array $filters): StreamedResponse
    {
        return $this->exportStreamer->generateExportResponse($this->buildQuery($filters), $filters);
    }

    public function importCsv(UploadedFile $file, User $user, string $formatKey): array
    {
        return (new DocumentImportPipeline($this->formats))->execute($file, $user, $formatKey);
    }

    public function buildImportFeedback(array $result): array
    {
        return $this->feedbackFormatter->format($result);
    }
}
