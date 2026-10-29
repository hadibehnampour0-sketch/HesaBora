<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

use App\Models\User;

/**
 * Clean-room implementation of Parsian Accounting Software Interchange format.
 *
 * Coordinates dataset detection and delegates to specialized ingestion pipelines
 * for transaction journals and trial balance subject trees.
 *
 * License: MIT
 */
class ParsianImportFormat extends DocumentImportFormat
{
    public const FORMAT_KEY = 'parsian';

    public function key(): string
    {
        return self::FORMAT_KEY;
    }

    public function label(): string
    {
        return __('Parsian');
    }

    public function matches(array $headers): bool
    {
        $cols = collect($headers)->map(fn ($h) => trim((string) $h));

        return $cols->contains('Sanad_Num') || ($cols->contains('KolCode') && $cols->contains('KolName'));
    }

    public function import(array $rows, User $user): array
    {
        $this->initResult();

        if (blank($rows)) {
            return $this->result;
        }

        $first = $rows[0] ?? [];

        if (isset($first['Sanad_Num'])) {
            return (new ParsianTransactionIngestor($this->subjects, $this))->process($rows, $user);
        }

        if (isset($first['KolCode']) && isset($first['KolName'])) {
            return (new ParsianTrialBalanceIngestor($this->subjects, $this))->process($rows);
        }

        return $this->result;
    }
}
