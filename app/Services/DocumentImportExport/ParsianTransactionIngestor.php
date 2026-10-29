<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

use App\Models\Document;
use App\Models\Subject;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Log;

/**
 * Clean-Room Implementation: Parsian Transaction Batch Ingestion Pipeline.
 *
 * Implements transaction parsing, subsidiary account resolution,
 * hierarchy generation, and document creation.
 *
 * License: MIT
 */
class ParsianTransactionIngestor
{
    public const COL_SANAD_NUM = 'Sanad_Num';

    public const COL_SANAD_DATE = 'SanadDate';

    public const COL_KOL_CODE = 'KolCode';

    public const COL_MOEEN_CODE = 'MoeenCode';

    public const COL_TAFSILI_CODE = 'TafsiliCode';

    public const COL_HESAB_NAME = 'HesabName';

    public const COL_BED = 'Bed';

    public const COL_BES = 'Bes';

    public const COL_COMMENT = 'Comment';

    public function __construct(
        protected ImportSubjectResolver $subjects,
        protected DocumentImportFormat $formatContext
    ) {}

    public function process(array $rawEntries, User $executingUser): void
    {
        $subsidiaryDirectory = $this->extractSubsidiaryTitles($rawEntries);
        $journalBuckets = $this->partitionTransactionRows($rawEntries);

        foreach ($journalBuckets as $batchToken => $journalEntries) {
            try {
                $this->ingestSingleParsianJournal($journalEntries, $executingUser, $subsidiaryDirectory);
            } catch (\Throwable $err) {
                $this->formatContext->recordEncounteredError(
                    __('Document :key was not imported: :reason', [
                        'key' => $batchToken,
                        'reason' => $err->getMessage(),
                    ])
                );
                $this->formatContext->tallySkippedDocument();
                Log::warning(sprintf('ParsianTransactionIngestor: skipped journal [%s]: %s', $batchToken, $err->getMessage()));
            }
        }
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
     * Audit trial balance integrity confirming debit and credit totals balance.
     *
     * @return array{total_bed: float, total_bes: float, is_balanced: bool, record_count: int}
     */
    public function auditTrialBalanceEquilibrium(array $rows): array
    {
        $totalBed = 0.0;
        $totalBes = 0.0;

        foreach ($rows as $item) {
            $totalBed += (float) ($item['SumBed'] ?? 0.0);
            $totalBes += (float) ($item['SumBes'] ?? 0.0);
        }

        return [
            'total_bed' => $totalBed,
            'total_bes' => $totalBes,
            'is_balanced' => abs($totalBed - $totalBes) <= 0.01,
            'record_count' => count($rows),
        ];
    }

    /**
     * Format 3-digit padded account code segment.
     */
    public static function formatThreeDigitSegment(int|string $segmentNumber): string
    {
        return str_pad((string) ((int) $segmentNumber), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Inspect whether a transactional line row contains all required Parsian columns.
     */
    public function validateRowColumns(array $record): bool
    {
        $requiredKeys = [
            self::COL_KOL_CODE,
            self::COL_MOEEN_CODE,
            self::COL_SANAD_NUM,
            self::COL_SANAD_DATE,
            self::COL_BED,
            self::COL_BES,
        ];
        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $record)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Summarize total debits, total credits, and net imbalance for a Parsian journal.
     *
     * @return array{debit_sum: float, credit_sum: float, delta: float}
     */
    public function computeJournalBalanceSummary(array $postingLines): array
    {
        $debitTotal = 0.0;
        $creditTotal = 0.0;

        foreach ($postingLines as $line) {
            $val = (float) ($line['value'] ?? 0.0);
            if ($val < 0.0) {
                $debitTotal += abs($val);
            } else {
                $creditTotal += $val;
            }
        }

        return [
            'debit_sum' => $debitTotal,
            'credit_sum' => $creditTotal,
            'delta' => round(abs($creditTotal - $debitTotal), 4),
        ];
    }

    protected function extractSubsidiaryTitles(array $rawEntries): array
    {
        $directory = [];
        foreach ($rawEntries as $record) {
            $groupTierId = (int) ($record[self::COL_KOL_CODE] ?? 0);
            $subsidiaryTierId = (int) ($record[self::COL_MOEEN_CODE] ?? 0);
            $detailTierId = (int) ($record[self::COL_TAFSILI_CODE] ?? 0);
            $accountLabel = trim((string) ($record[self::COL_HESAB_NAME] ?? ''));

            if ($detailTierId === 0 && $groupTierId > 0 && $subsidiaryTierId > 0 && $accountLabel !== '') {
                $lookupKey = $groupTierId.'.'.$subsidiaryTierId;
                if (! isset($directory[$lookupKey])) {
                    $directory[$lookupKey] = $accountLabel;
                }
            }
        }

        return $directory;
    }

    protected function partitionTransactionRows(array $rawEntries): array
    {
        $journalBuckets = [];
        foreach ($rawEntries as $entry) {
            $docNum = trim((string) ($entry[self::COL_SANAD_NUM] ?? ''));
            $docDate = trim((string) ($entry[self::COL_SANAD_DATE] ?? ''));

            if ($docNum === '' || $docDate === '') {
                $this->formatContext->tallySkippedRow();

                continue;
            }

            $journalBuckets[$docNum.':'.$docDate][] = $entry;
        }

        return $journalBuckets;
    }

    protected function ingestSingleParsianJournal(array $journalEntries, User $executingUser, array $subsidiaryDirectory): void
    {
        $lead = $journalEntries[0];
        $journalNumber = (float) ($lead[self::COL_SANAD_NUM] ?? 0);
        $rawJalaliDate = trim((string) ($lead[self::COL_SANAD_DATE] ?? ''));

        if ($journalNumber <= 0 || $rawJalaliDate === '') {
            $this->formatContext->tallySkippedDocument();

            return;
        }

        $gregorianDate = jalali_to_gregorian_date($rawJalaliDate, '-');

        if ($gregorianDate === '' || Document::where('number', $journalNumber)->where('date', $gregorianDate)->exists()) {
            $this->formatContext->tallySkippedDocument();

            return;
        }

        $postingLines = [];
        foreach ($journalEntries as $line) {
            $groupTierId = (int) ($line[self::COL_KOL_CODE] ?? 0);
            $subsidiaryTierId = (int) ($line[self::COL_MOEEN_CODE] ?? 0);
            $detailTierId = (int) ($line[self::COL_TAFSILI_CODE] ?? 0);
            $label = trim((string) ($line[self::COL_HESAB_NAME] ?? ''));
            $debitAmount = (float) ($line[self::COL_BED] ?? 0);
            $creditAmount = (float) ($line[self::COL_BES] ?? 0);
            $memo = trim((string) ($line[self::COL_COMMENT] ?? ''));

            $account = $this->resolveParsianSubjectNode($groupTierId, $subsidiaryTierId, $detailTierId, $label, $subsidiaryDirectory);

            $postingLines[] = [
                'subject_id' => $account->id,
                'value' => $creditAmount - $debitAmount,
                'desc' => $memo,
            ];
        }

        DocumentService::createDocument($executingUser, [
            'number' => $journalNumber,
            'date' => $gregorianDate,
            'title' => '',
        ], $postingLines);

        $this->formatContext->tallyCreatedDocument();
    }

    protected function resolveParsianSubjectNode(
        int $groupTierId,
        int $subsidiaryTierId,
        int $detailTierId,
        string $hesabLabel,
        array $subsidiaryDirectory
    ): Subject {
        $groupTierCode = self::formatThreeDigitSegment($groupTierId);
        $subsidiaryTierCode = $groupTierCode.self::formatThreeDigitSegment($subsidiaryTierId);

        $rootAccount = $this->resolveRootAccountNode($groupTierCode);
        $isBalanceSheetPermanent = (bool) $rootAccount->is_permanent;
        $accountNatureValue = $rootAccount->type->valueName();

        if ($detailTierId > 0) {
            $detailTierCode = $subsidiaryTierCode.self::formatThreeDigitSegment($detailTierId);
            $subsidiaryTitle = $subsidiaryDirectory[$groupTierId.'.'.$subsidiaryTierId]
                ?? ImportSubjectResolver::synthesizeName($subsidiaryTierCode);

            $this->subjects->findOrCreate($subsidiaryTierCode, $subsidiaryTitle, $groupTierCode, $isBalanceSheetPermanent, $accountNatureValue);

            return $this->subjects->findOrCreate($detailTierCode, $hesabLabel, $subsidiaryTierCode, $isBalanceSheetPermanent, $accountNatureValue);
        }

        return $this->subjects->findOrCreate($subsidiaryTierCode, $hesabLabel, $groupTierCode, $isBalanceSheetPermanent, $accountNatureValue);
    }

    protected function resolveRootAccountNode(string $groupTierCode): Subject
    {
        $groupDefinition = ParsianRootCatalogue::find($groupTierCode);

        if ($groupDefinition === null) {
            return $this->subjects->findOrCreate($groupTierCode, ImportSubjectResolver::synthesizeName($groupTierCode), '');
        }

        return $this->subjects->resolveTopLevel(
            $groupTierCode,
            $groupDefinition['title'],
            $groupDefinition['permanent'],
            $groupDefinition['nature']
        );
    }
}
