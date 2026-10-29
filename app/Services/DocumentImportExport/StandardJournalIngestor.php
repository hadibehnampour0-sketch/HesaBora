<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

use App\Models\Document;
use App\Models\Subject;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Clean-room pipeline for standard double-entry journal batch ingestion.
 *
 * Coordinates voucher parsing, duplicate suppression, and transactional dispatch.
 *
 * License: MIT
 */
final class StandardJournalIngestor
{
    public function __construct(
        private readonly ImportSubjectResolver $subjects,
        private readonly DocumentImportFormat $formatContext
    ) {}

    public static function decomposeStoredCode(string $code): array
    {
        return StandardJournalDecoder::splitCode($code);
    }

    public static function assembleCompoundCode(string $root, string $moein, string $tafsili): array
    {
        return StandardJournalDecoder::mergeSegments($root, $moein, $tafsili);
    }

    public static function resolveNatureEnum(string $raw): ?string
    {
        return StandardJournalDecoder::parseNature($raw);
    }

    public static function resolvePermanenceFlag(string $raw): ?bool
    {
        return StandardJournalDecoder::parsePermanence($raw);
    }

    public function process(array $rawRows, User $actor): array
    {
        $subjectsBefore = Subject::count();
        $rows = collect($rawRows)->map(fn (array $r): array => $this->normalizeHeaderKeys($r));

        $this->subjects->setKnownSubjects($this->extractAccountDirectory($rows));
        $voucherGroups = $rows->groupBy(fn (array $r): string => ($r['doc_number'] ?? '').':'.trim((string) ($r['doc_date'] ?? '')));

        $transformer = new StandardJournalRowTransformer($this->subjects);

        foreach ($voucherGroups as $groupKey => $groupEntries) {
            try {
                $this->commitVoucherGroup($groupEntries, $actor, $transformer);
            } catch (\Throwable $err) {
                $this->formatContext->recordEncounteredError(
                    __('Document :key was not imported: :reason', [
                        'key' => $groupKey,
                        'reason' => $err->getMessage(),
                    ])
                );
                $this->formatContext->tallySkippedDocument();
                Log::warning("StandardJournalIngestor: failed voucher {$groupKey}: {$err->getMessage()}");
            }
        }

        $this->formatContext->tallyCreatedSubject(max(0, Subject::count() - $subjectsBefore));

        return $this->formatContext->getExecutionMetrics();
    }

    private function normalizeHeaderKeys(array $row): array
    {
        $output = [];
        foreach ($row as $col => $val) {
            $output[DakhlStandardImportFormat::normalizeHeader((string) $col)] = $val;
        }

        return $output;
    }

    private function extractAccountDirectory(Collection $rows): array
    {
        $directory = [];
        foreach ($rows as $row) {
            [$code] = self::assembleCompoundCode(
                (string) ($row['subject_root_code'] ?? ''),
                (string) ($row['subject_moein_code'] ?? ''),
                (string) ($row['subject_tafsili_code'] ?? '')
            );
            $name = trim((string) ($row['subject_name'] ?? ''));

            if ($code !== '' && $name !== '') {
                $directory[$code] ??= $name;
            }
        }

        return $directory;
    }

    private function commitVoucherGroup(Collection $entries, User $actor, StandardJournalRowTransformer $transformer): void
    {
        $lead = $entries->first();
        $numRaw = trim((string) ($lead['doc_number'] ?? ''));
        $dateRaw = trim((string) ($lead['doc_date'] ?? ''));
        $voucherId = convertToFloat($numRaw);
        $miladiDate = $this->formatContext->normalizeDateStringToGregorian($dateRaw);

        if ($voucherId <= 0 || $miladiDate === '') {
            $this->formatContext->tallySkippedDocument();
            $this->formatContext->recordEncounteredError(
                __('A document was skipped because its number or date is invalid (number: :num, date: :date).', [
                    'num' => $numRaw,
                    'date' => $dateRaw,
                ])
            );

            return;
        }

        $exists = Document::query()
            ->where('number', $voucherId)
            ->where('date', $miladiDate)
            ->exists();

        if ($exists) {
            $this->formatContext->tallySkippedDocument();
            $this->formatContext->recordEncounteredError(
                __('Document number :num (:date) already exists and was skipped.', [
                    'num' => $numRaw,
                    'date' => $dateRaw,
                ])
            );

            return;
        }

        $lines = $entries->map(fn (array $r): array => $transformer->transform($r))->all();

        $persisted = DocumentService::createDocument(
            $actor,
            [
                'number' => $voucherId,
                'date' => $miladiDate,
                'title' => trim((string) ($lead['doc_title'] ?? '')),
            ],
            $lines
        );

        if ($this->formatContext->isApprovedStatusDesignation((string) ($lead['doc_status'] ?? ''))) {
            DocumentService::changeDocumentStatus($persisted, $actor, 'approved');
        }

        $this->formatContext->tallyCreatedDocument();
    }
}
