<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

/**
 * Clean-room row transformer for standard double-entry journal records.
 *
 * Resolves account subject nodes and computes transaction debit/credit line balances.
 *
 * License: MIT
 */
final class StandardJournalRowTransformer
{
    public function __construct(
        private readonly ImportSubjectResolver $subjects
    ) {}

    public function transform(array $row): array
    {
        [$code, $parent] = StandardJournalDecoder::mergeSegments(
            (string) ($row['subject_root_code'] ?? ''),
            (string) ($row['subject_moein_code'] ?? ''),
            (string) ($row['subject_tafsili_code'] ?? '')
        );

        $subject = $this->subjects->findOrCreate(
            $code,
            trim((string) ($row['subject_name'] ?? '')),
            $parent,
            StandardJournalDecoder::parsePermanence((string) ($row['subject_is_permanent'] ?? '')),
            StandardJournalDecoder::parseNature((string) ($row['subject_type'] ?? ''))
        );

        $debit = convertToFloat($row['debit'] ?? 0);
        $credit = convertToFloat($row['credit'] ?? 0);

        return [
            'subject_id' => $subject->id,
            'value' => $credit - $debit,
            'desc' => trim((string) ($row['transaction_desc'] ?? '')),
        ];
    }
}
