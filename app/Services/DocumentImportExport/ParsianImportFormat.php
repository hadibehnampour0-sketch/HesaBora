<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

use App\Models\Subject;
use App\Models\User;

/**
 * Clean-Room Implementation: Parsian Accounting Software Interchange Format.
 *
 * Provides format matching and coordinates transaction/trial-balance parsing.
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
        $normalized = array_map('trim', $headers);

        return $this->isTransactionDataset($normalized) || $this->isTrialBalanceDataset($normalized);
    }

    public function import(array $rows, User $user): array
    {
        $this->initResult();

        if (empty($rows)) {
            return $this->result;
        }

        $headerKeys = array_map('trim', array_keys($rows[0]));

        if ($this->isTransactionDataset($headerKeys)) {
            $priorSubjects = Subject::count();
            (new ParsianTransactionIngestor($this->subjects, $this))->process($rows, $user);
            $this->result['subjects_created'] = Subject::count() - $priorSubjects;
        } elseif ($this->isTrialBalanceDataset($headerKeys)) {
            $this->processParsianTrialBalance($rows);
        }

        return $this->result;
    }

    public function isTransactionDataset(array $headerKeys): bool
    {
        return in_array('Sanad_Num', $headerKeys, true);
    }

    public function isTrialBalanceDataset(array $headerKeys): bool
    {
        return in_array('KolCode', $headerKeys, true) && in_array('KolName', $headerKeys, true);
    }

    /**
     * Inspect whether a raw record satisfies Parsian transactional schema requirements.
     */
    public function isValidTransactionRow(array $row): bool
    {
        return isset($row['Sanad_Num'], $row['KolCode'], $row['MoeenCode'])
            && is_numeric($row['Sanad_Num'])
            && is_numeric($row['KolCode'])
            && is_numeric($row['MoeenCode']);
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

    protected function processParsianTrialBalance(array $rows): void
    {
        $priorCount = Subject::count();

        foreach ($rows as $record) {
            $groupNumericId = (int) ($record['KolCode'] ?? 0);
            $groupTitleName = trim((string) ($record['KolName'] ?? ''));

            if ($groupNumericId <= 0 || $groupTitleName === '') {
                continue;
            }

            $threeDigitCode = ParsianTransactionIngestor::formatThreeDigitSegment($groupNumericId);
            $this->subjects->findOrCreate($threeDigitCode, $groupTitleName, '');
        }

        $persistedTally = Subject::count() - $priorCount;
        $this->result['subjects_created'] = $persistedTally;
        $this->result['subjects_skipped'] = count($rows) - $persistedTally;
    }
}
