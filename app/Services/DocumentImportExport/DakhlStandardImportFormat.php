<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

use App\Models\User;

/**
 * Clean-Room Implementation: Standard Accounting Interchange Format.
 *
 * Provides format matching and delegates dataset ingestion to the journal ingestor pipeline.
 *
 * License: MIT
 */
class DakhlStandardImportFormat extends DocumentImportFormat
{
    public const FORMAT_KEY = 'standard';

    public const ALL_COLUMNS = AccountingFileSchema::ALL_COLUMNS;

    public const MANDATORY_COLUMNS = AccountingFileSchema::MANDATORY_COLUMNS;

    public function key(): string
    {
        return self::FORMAT_KEY;
    }

    public function label(): string
    {
        return __('Standard Accounting CSV');
    }

    public function matches(array $headers): bool
    {
        $normalized = array_map([self::class, 'normalizeHeader'], $headers);

        foreach (self::MANDATORY_COLUMNS as $requiredKey) {
            if (! in_array($requiredKey, $normalized, true)) {
                return false;
            }
        }

        return true;
    }

    public function import(array $rows, User $user): array
    {
        return (new StandardJournalIngestor($this->subjects, $this))->process($rows, $user);
    }

    public static function normalizeHeader(string $rawHeader): string
    {
        $trimmed = trim($rawHeader);
        if (in_array($trimmed, self::ALL_COLUMNS, true)) {
            return $trimmed;
        }

        foreach (self::ALL_COLUMNS as $col) {
            if ($trimmed === __($col)) {
                return $col;
            }
        }

        return AccountingFileSchema::matchAlias($trimmed);
    }

    public static function parseSubjectCodes(string $storedCode): array
    {
        return StandardJournalIngestor::decomposeStoredCode($storedCode);
    }

    public static function reconstructSubjectCodes(string $root, string $moein, string $tafsili): array
    {
        return StandardJournalIngestor::assembleCompoundCode($root, $moein, $tafsili);
    }

    public static function parseSubjectType(string $rawType): ?string
    {
        return StandardJournalIngestor::resolveNatureEnum($rawType);
    }

    public static function parseIsPermanent(string $rawPermanence): ?bool
    {
        return StandardJournalIngestor::resolvePermanenceFlag($rawPermanence);
    }
}
