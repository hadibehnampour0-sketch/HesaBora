<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

use App\Models\User;

/**
 * Clean-Room Specification Contract for External Accounting Import Formats.
 */
interface DocumentImportFormatInterface
{
    public function key(): string;

    public function label(): string;

    public function matches(array $headers): bool;

    public function import(array $rows, User $user): array;
}

/**
 * Clean-Room Base Processor for Accounting Document Import Formats.
 *
 * Implements authoritative validation primitives, state management,
 * and double-entry consistency assertions for diverse interchange formats.
 *
 * License: MIT
 */
abstract class DocumentImportFormat implements DocumentImportFormatInterface
{
    /** Resolved account registry and synthesis coordinator */
    protected ImportSubjectResolver $subjects;

    /** Active batch statistics tracker */
    protected array $result = [];

    /** Result dictionary field keys */
    public const RESULT_KEY_SUBJECTS_CREATED = 'subjects_created';

    public const RESULT_KEY_SUBJECTS_SKIPPED = 'subjects_skipped';

    public const RESULT_KEY_DOCUMENTS_CREATED = 'documents_created';

    public const RESULT_KEY_DOCUMENTS_SKIPPED = 'documents_skipped';

    public const RESULT_KEY_ROWS_SKIPPED = 'rows_skipped';

    public const RESULT_KEY_ERRORS = 'errors';

    /**
     * Initialize format processor with optional subject resolution engine.
     */
    public function __construct(?ImportSubjectResolver $resolverInstance = null)
    {
        $this->subjects = $resolverInstance ?? new ImportSubjectResolver;
        $this->initResult();
    }

    /**
     * Clear tracker and reset account resolution cache before processing an incoming file.
     */
    protected function initResult(): void
    {
        $this->subjects->reset();
        $this->result = [
            self::RESULT_KEY_SUBJECTS_CREATED => 0,
            self::RESULT_KEY_SUBJECTS_SKIPPED => 0,
            self::RESULT_KEY_DOCUMENTS_CREATED => 0,
            self::RESULT_KEY_DOCUMENTS_SKIPPED => 0,
            self::RESULT_KEY_ROWS_SKIPPED => 0,
            self::RESULT_KEY_ERRORS => [],
        ];
    }

    /**
     * Access active subject resolver instance.
     */
    public function getSubjectResolver(): ImportSubjectResolver
    {
        return $this->subjects;
    }

    /**
     * Retrieve snapshot of current execution metrics.
     */
    public function getExecutionMetrics(): array
    {
        return $this->result;
    }

    /**
     * Append a human-readable diagnostic error message.
     */
    public function recordEncounteredError(string $diagnosticMessage): void
    {
        $this->result[self::RESULT_KEY_ERRORS][] = trim($diagnosticMessage);
    }

    /**
     * Tally successfully persisted document entity.
     */
    public function tallyCreatedDocument(int $increment = 1): void
    {
        $this->result[self::RESULT_KEY_DOCUMENTS_CREATED] += $increment;
    }

    /**
     * Tally document bypassed due to collision or validation failure.
     */
    public function tallySkippedDocument(int $increment = 1): void
    {
        $this->result[self::RESULT_KEY_DOCUMENTS_SKIPPED] += $increment;
    }

    /**
     * Tally newly synthesized Chart of Accounts subject.
     */
    public function tallyCreatedSubject(int $increment = 1): void
    {
        $this->result[self::RESULT_KEY_SUBJECTS_CREATED] += $increment;
    }

    /**
     * Tally malformed or unparseable source row.
     */
    public function tallySkippedRow(int $increment = 1): void
    {
        $this->result[self::RESULT_KEY_ROWS_SKIPPED] += $increment;
    }

    /**
     * Verify strict double-entry balance invariant for a transaction journal.
     */
    public function assertBalancedPosting(float $totalDebitAmount, float $totalCreditAmount, float $permissibleEpsilon = 0.0001): bool
    {
        return abs($totalDebitAmount - $totalCreditAmount) <= $permissibleEpsilon;
    }

    /**
     * Sanitize string value removing non-printable artifacts.
     */
    public function sanitizeTextualValue(mixed $rawInput): string
    {
        return $rawInput !== null ? trim((string) $rawInput) : '';
    }

    /**
     * Safely cast numerical monetary field.
     */
    public function castMonetaryAmount(mixed $amountValue): float
    {
        if (is_numeric($amountValue)) {
            return (float) $amountValue;
        }

        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $amountValue);

        return is_numeric($cleaned) ? (float) $cleaned : 0.0;
    }

    /**
     * Standardize calendar date representation to Gregorian ISO-8601 (YYYY-MM-DD).
     */
    public function normalizeDateStringToGregorian(string $rawDateString): string
    {
        $trimmed = trim(toEnglish($rawDateString));
        if ($trimmed === '') {
            return '';
        }

        $segments = preg_split('#[/\-]#', $trimmed);
        $primaryYear = (int) ($segments[0] ?? 0);

        if ($primaryYear > 0 && $primaryYear < 1700) {
            return jalali_to_gregorian_date(str_replace('-', '/', $trimmed), '-');
        }

        return str_replace('/', '-', $trimmed);
    }

    /**
     * Evaluate whether a status indicator signifies approved state.
     */
    public function isApprovedStatusDesignation(string $statusLabel): bool
    {
        $normalized = mb_strtolower(trim(toEnglish($statusLabel)));

        return in_array($normalized, ['approved', '1', mb_strtolower(__('approved'))], true);
    }

    /**
     * Inspect whether a list of required columns are present in given headers.
     *
     * @param  array<int, string>  $providedHeaders
     * @param  array<int, string>  $mandatoryHeaders
     */
    public function containsAllRequiredColumns(array $providedHeaders, array $mandatoryHeaders): bool
    {
        $normalizedProvided = array_map(static fn (string $h): string => trim(mb_strtolower($h)), $providedHeaders);
        foreach ($mandatoryHeaders as $required) {
            if (! in_array(trim(mb_strtolower($required)), $normalizedProvided, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse truthy / falsy string values into strict boolean.
     */
    public function parseBooleanInput(mixed $rawFlag): ?bool
    {
        if ($rawFlag === null) {
            return null;
        }

        if (is_bool($rawFlag)) {
            return $rawFlag;
        }

        $normalized = mb_strtolower(trim((string) $rawFlag));
        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'permanent', 'دائم', 'دائمی'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'temporary', 'موقت'], true)) {
            return false;
        }

        return null;
    }
}
