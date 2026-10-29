<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

use App\Models\Subject;

/**
 * Clean-room pipeline for importing Parsian software trial balance subject trees.
 *
 * Reconstructs 3-digit root account nodes from KolCode/KolName declarations.
 *
 * License: MIT
 */
final class ParsianTrialBalanceIngestor
{
    public function __construct(
        private readonly ImportSubjectResolver $resolver,
        private readonly DocumentImportFormat $format
    ) {}

    public function process(array $records): array
    {
        $initialTotal = Subject::count();

        foreach ($records as $record) {
            $numeric = (int) ($record['KolCode'] ?? 0);
            $title = trim((string) ($record['KolName'] ?? ''));

            if ($numeric > 0 && $title !== '') {
                $this->resolver->findOrCreate(sprintf('%03d', $numeric), $title, '');
            }
        }

        $added = Subject::count() - $initialTotal;
        $this->format->tallyCreatedSubject($added);
        $this->format->tallySkippedSubject(count($records) - $added);

        return $this->format->getExecutionMetrics();
    }
}
