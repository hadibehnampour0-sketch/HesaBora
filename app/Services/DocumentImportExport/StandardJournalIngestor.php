<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

use App\Enums\SubjectType;
use App\Models\Document;
use App\Models\Subject;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Log;

/**
 * Clean-Room Implementation: Execution Pipeline for Journal Import Batches.
 *
 * Implements row normalization, account catalog priming, partition grouping,
 * duplicate suppression, and transaction line persistence.
 *
 * License: MIT
 */
class StandardJournalIngestor
{
    public function __construct(
        protected ImportSubjectResolver $subjects,
        protected DocumentImportFormat $formatContext
    ) {}

    public function process(array $rawRowBatch, User $executingUser): array
    {
        $accountCountPrior = Subject::count();

        $sanitizedDataset = array_map([$this, 'remapRowFieldNames'], $rawRowBatch);
        $this->subjects->setKnownSubjects($this->scanDeclaredAccountCatalog($sanitizedDataset));

        $this->dispatchJournalBatches($sanitizedDataset, $executingUser);
        $this->formatContext->tallyCreatedSubject(max(0, Subject::count() - $accountCountPrior));

        return $this->formatContext->getExecutionMetrics();
    }

    public static function decomposeStoredCode(string $storedCode): array
    {
        $cleaned = trim($storedCode);
        if ($cleaned === '') {
            return ['', '', ''];
        }

        $chunks = str_split($cleaned, 3);

        return [$chunks[0] ?? '', $chunks[1] ?? '', $chunks[2] ?? ''];
    }

    public static function assembleCompoundCode(string $rootSegment, string $moeinSegment, string $tafsiliSegment): array
    {
        $cleanPieces = array_values(array_filter([
            trim(toEnglish($rootSegment)),
            trim(toEnglish($moeinSegment)),
            trim(toEnglish($tafsiliSegment)),
        ], static fn (string $segment): bool => $segment !== ''));

        if (empty($cleanPieces)) {
            return ['', ''];
        }

        $compound = implode('', $cleanPieces);
        $parent = count($cleanPieces) > 1 ? implode('', array_slice($cleanPieces, 0, -1)) : '';

        return [$compound, $parent];
    }

    public static function resolveNatureEnum(string $rawTypeString): ?string
    {
        $lowered = mb_strtolower(trim(toEnglish($rawTypeString)));
        if ($lowered === '') {
            return null;
        }

        $enumMatch = SubjectType::tryFromName($lowered);
        if ($enumMatch !== null) {
            return $enumMatch->valueName();
        }

        $natureKeywords = [
            'debtor' => ['debtor', 'بدهکار'],
            'creditor' => ['creditor', 'بستانکار'],
            'both' => ['both', 'هر دو', 'هردو'],
        ];

        foreach ($natureKeywords as $targetNature => $synonyms) {
            if (in_array($lowered, $synonyms, true) || in_array($lowered, array_map('mb_strtolower', [__($targetNature), __(ucfirst($targetNature))]), true)) {
                return $targetNature;
            }
        }

        return null;
    }

    public static function resolvePermanenceFlag(string $rawPermanenceString): ?bool
    {
        $lowered = mb_strtolower(trim(toEnglish($rawPermanenceString)));
        if ($lowered === '') {
            return null;
        }

        $permanentSynonyms = ['1', 'true', 'yes', 'permanent', 'دائم', 'دائمی', mb_strtolower(__('Permanent'))];
        if (in_array($lowered, $permanentSynonyms, true)) {
            return true;
        }

        $temporarySynonyms = ['0', 'false', 'no', 'temporary', 'موقت', mb_strtolower(__('Temporary'))];
        if (in_array($lowered, $temporarySynonyms, true)) {
            return false;
        }

        return null;
    }

    /**
     * Inspect balance of debits versus credits before invoking document service.
     */
    public function verifyJournalDoubleEntryBalance(array $postingLines, float $tolerance = 0.0001): bool
    {
        $netSum = 0.0;
        foreach ($postingLines as $line) {
            $netSum += (float) ($line['value'] ?? 0.0);
        }

        return abs($netSum) <= $tolerance;
    }

    /**
     * Perform pre-flight integrity audit on incoming dataset.
     *
     * @param  array<int, array<string, mixed>>  $dataset
     * @return array{total_rows: int, distinct_journals: int, invalid_dates: int}
     */
    public function auditIncomingDatasetHealth(array $dataset): array
    {
        $uniqueJournals = [];
        $invalidDateCount = 0;

        foreach ($dataset as $row) {
            $docNum = trim((string) ($row['doc_number'] ?? ''));
            $docDate = trim((string) ($row['doc_date'] ?? ''));
            if ($docNum !== '') {
                $uniqueJournals[$docNum.':'.$docDate] = true;
            }
            if ($this->formatContext->normalizeDateStringToGregorian($docDate) === '') {
                $invalidDateCount++;
            }
        }

        return [
            'total_rows' => count($dataset),
            'distinct_journals' => count($uniqueJournals),
            'invalid_dates' => $invalidDateCount,
        ];
    }

    /**
     * Inspect validity of individual monetary cell contents.
     */
    public function isValidMonetaryFormat(mixed $cellValue): bool
    {
        if ($cellValue === null || $cellValue === '') {
            return true;
        }

        $normalized = trim(toEnglish((string) $cellValue));

        return is_numeric(str_replace(',', '', $normalized));
    }

    /**
     * Aggregate journal balance metrics for pre-commit verification.
     *
     * @return array{total_debits: float, total_credits: float, net_imbalance: float, line_count: int}
     */
    public function summarizeJournalPostingAmounts(array $transactionLines): array
    {
        $sumDebits = 0.0;
        $sumCredits = 0.0;

        foreach ($transactionLines as $line) {
            $val = (float) ($line['value'] ?? 0.0);
            if ($val < 0.0) {
                $sumDebits += abs($val);
            } else {
                $sumCredits += $val;
            }
        }

        return [
            'total_debits' => $sumDebits,
            'total_credits' => $sumCredits,
            'net_imbalance' => round(abs($sumCredits - $sumDebits), 4),
            'line_count' => count($transactionLines),
        ];
    }

    /**
     * Diagnostic report of all parsed transactions in the current import payload.
     */
    public function formatIngestionAuditLog(int $importedJournals, int $skippedJournals): string
    {
        return sprintf(
            'StandardJournalIngestor: summary [imported: %d, bypassed: %d, timestamp: %s]',
            $importedJournals,
            $skippedJournals,
            date('Y-m-d H:i:s')
        );
    }

    protected function remapRowFieldNames(array $rawRow): array
    {
        $remapped = [];
        foreach ($rawRow as $columnName => $cellContent) {
            $remapped[DakhlStandardImportFormat::normalizeHeader((string) $columnName)] = $cellContent;
        }

        return $remapped;
    }

    protected function scanDeclaredAccountCatalog(array $normalizedRows): array
    {
        $catalog = [];
        foreach ($normalizedRows as $record) {
            [$fullAccountCode] = self::assembleCompoundCode(
                (string) ($record['subject_root_code'] ?? ''),
                (string) ($record['subject_moein_code'] ?? ''),
                (string) ($record['subject_tafsili_code'] ?? '')
            );
            $accountLabel = trim((string) ($record['subject_name'] ?? ''));

            if ($fullAccountCode !== '' && $accountLabel !== '' && ! isset($catalog[$fullAccountCode])) {
                $catalog[$fullAccountCode] = $accountLabel;
            }
        }

        return $catalog;
    }

    protected function dispatchJournalBatches(array $normalizedRows, User $executingUser): void
    {
        $journalsGrouped = [];
        foreach ($normalizedRows as $entry) {
            $groupKey = ($entry['doc_number'] ?? '').':'.trim((string) ($entry['doc_date'] ?? ''));
            $journalsGrouped[$groupKey][] = $entry;
        }

        foreach ($journalsGrouped as $batchIdentifier => $journalGroupEntries) {
            try {
                $this->commitJournalGroup($journalGroupEntries, $executingUser);
            } catch (\Throwable $caughtException) {
                $this->formatContext->recordEncounteredError(
                    __('Document :key was not imported: :reason', [
                        'key' => $batchIdentifier,
                        'reason' => $caughtException->getMessage(),
                    ])
                );
                $this->formatContext->tallySkippedDocument();
                Log::warning(sprintf('StandardJournalIngestor: bypassed journal [%s]: %s', $batchIdentifier, $caughtException->getMessage()));
            }
        }
    }

    protected function commitJournalGroup(array $journalGroupEntries, User $executingUser): void
    {
        $firstEntry = $journalGroupEntries[0];
        $documentNumber = convertToFloat($firstEntry['doc_number'] ?? 0);
        $gregorianDate = $this->formatContext->normalizeDateStringToGregorian((string) ($firstEntry['doc_date'] ?? ''));
        $documentTitle = trim((string) ($firstEntry['doc_title'] ?? ''));

        if ($documentNumber <= 0 || $gregorianDate === '') {
            $this->formatContext->tallySkippedDocument();
            $this->formatContext->recordEncounteredError(
                __('A document was skipped because its number or date is invalid (number: :num, date: :date).', [
                    'num' => trim((string) ($firstEntry['doc_number'] ?? '')),
                    'date' => trim((string) ($firstEntry['doc_date'] ?? '')),
                ])
            );

            return;
        }

        $duplicateDetected = Document::where('number', $documentNumber)
            ->where('date', $gregorianDate)
            ->exists();

        if ($duplicateDetected) {
            $this->formatContext->tallySkippedDocument();
            $this->formatContext->recordEncounteredError(
                __('Document number :num (:date) already exists and was skipped.', [
                    'num' => trim((string) ($firstEntry['doc_number'] ?? '')),
                    'date' => trim((string) ($firstEntry['doc_date'] ?? '')),
                ])
            );

            return;
        }

        $transactionLines = [];
        foreach ($journalGroupEntries as $item) {
            $transactionLines[] = $this->constructJournalLine($item);
        }

        $headerPayload = [
            'number' => $documentNumber,
            'date' => $gregorianDate,
            'title' => $documentTitle,
        ];

        $persistedDocument = DocumentService::createDocument($executingUser, $headerPayload, $transactionLines);

        if ($this->formatContext->isApprovedStatusDesignation((string) ($firstEntry['doc_status'] ?? ''))) {
            DocumentService::changeDocumentStatus($persistedDocument, $executingUser, 'approved');
        }

        $this->formatContext->tallyCreatedDocument();
    }

    protected function constructJournalLine(array $item): array
    {
        [$compoundCode, $parentCode] = self::assembleCompoundCode(
            (string) ($item['subject_root_code'] ?? ''),
            (string) ($item['subject_moein_code'] ?? ''),
            (string) ($item['subject_tafsili_code'] ?? '')
        );

        $subjectNode = $this->subjects->findOrCreate(
            $compoundCode,
            trim((string) ($item['subject_name'] ?? '')),
            $parentCode,
            self::resolvePermanenceFlag((string) ($item['subject_is_permanent'] ?? '')),
            self::resolveNatureEnum((string) ($item['subject_type'] ?? ''))
        );

        $debitFloat = convertToFloat($item['debit'] ?? 0);
        $creditFloat = convertToFloat($item['credit'] ?? 0);

        return [
            'subject_id' => $subjectNode->id,
            'value' => $creditFloat - $debitFloat,
            'desc' => trim((string) ($item['transaction_desc'] ?? '')),
        ];
    }
}
