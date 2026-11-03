<?php

declare(strict_types=1);

namespace Dakhl\Core\Numbering;

use Dakhl\Cleanroom\Core\Contracts\DocumentNumberingContract;
use Dakhl\Cleanroom\Core\Exceptions\InvalidBlockSizeException;
use Dakhl\Core\Support\Uuid;
use PDO;

final class DocumentNumberingService implements DocumentNumberingContract
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function allocateNextNumber(int $companyId, int $fiscalYearId, string $entityType): int
    {
        $this->ensureTenantAndFiscalYear($companyId, $fiscalYearId);

        $stmt = $this->pdo->prepare('
            SELECT COALESCE(MAX(range_end), 0)
            FROM numbering_leases
            WHERE company_id = ? AND fiscal_year_id = ? AND entity_type = ?
        ');
        $stmt->execute([$companyId, $fiscalYearId, $entityType]);
        $currentMax = (int) $stmt->fetchColumn();

        $nextNumber = $currentMax + 1;

        $insert = $this->pdo->prepare('
            INSERT INTO numbering_leases (uuid, company_id, fiscal_year_id, entity_type, device_id, range_start, range_end, next_number)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $insert->execute([
            Uuid::binary16(),
            $companyId,
            $fiscalYearId,
            $entityType,
            'SERVER',
            $nextNumber,
            $nextNumber,
            $nextNumber + 1,
        ]);

        return $nextNumber;
    }

    public function leaseNumberRange(
        int $companyId,
        int $fiscalYearId,
        string $entityType,
        string $deviceId,
        int $blockSize
    ): array {
        if ($blockSize < 1 || $blockSize > 500) {
            throw new InvalidBlockSizeException("Invalid block lease size: {$blockSize}. Block size must be between 1 and 500.");
        }

        $this->ensureTenantAndFiscalYear($companyId, $fiscalYearId);

        $stmt = $this->pdo->prepare('
            SELECT COALESCE(MAX(range_end), 0)
            FROM numbering_leases
            WHERE company_id = ? AND fiscal_year_id = ? AND entity_type = ?
        ');
        $stmt->execute([$companyId, $fiscalYearId, $entityType]);
        $currentMax = (int) $stmt->fetchColumn();

        $rangeStart = $currentMax + 1;
        $rangeEnd = $rangeStart + $blockSize - 1;

        $insert = $this->pdo->prepare('
            INSERT INTO numbering_leases (uuid, company_id, fiscal_year_id, entity_type, device_id, range_start, range_end, next_number)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $insert->execute([
            Uuid::binary16(),
            $companyId,
            $fiscalYearId,
            $entityType,
            $deviceId,
            $rangeStart,
            $rangeEnd,
            $rangeStart,
        ]);

        return [$rangeStart, $rangeEnd];
    }

    public function recordVoidNumber(
        int $companyId,
        int $fiscalYearId,
        string $entityType,
        int $number,
        string $reason
    ): void {
        $insert = $this->pdo->prepare('
            INSERT INTO audit_events (uuid, company_id, event_type, entity_type, entity_id, user_id, description, metadata)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $insert->execute([
            Uuid::binary16(),
            $companyId,
            'NUMBER_VOIDED',
            $entityType,
            $number,
            1,
            $reason,
            json_encode(['fiscal_year_id' => $fiscalYearId, 'number' => $number], JSON_THROW_ON_ERROR),
        ]);
    }

    private function ensureTenantAndFiscalYear(int $companyId, int $fiscalYearId): void
    {
        $cStmt = $this->pdo->prepare('SELECT id FROM companies WHERE id = ?');
        $cStmt->execute([$companyId]);
        if ($cStmt->fetchColumn() === false) {
            $cInsert = $this->pdo->prepare('INSERT INTO companies (id, uuid, name) VALUES (?, ?, ?)');
            $cInsert->execute([$companyId, Uuid::binary16(), "Company {$companyId}"]);
        }

        $fStmt = $this->pdo->prepare('SELECT id FROM fiscal_years WHERE id = ? OR (company_id = ? AND year_number = ?)');
        $fStmt->execute([$fiscalYearId, $companyId, $fiscalYearId]);
        if ($fStmt->fetchColumn() === false) {
            $fInsert = $this->pdo->prepare('INSERT INTO fiscal_years (id, uuid, company_id, year_number, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)');
            $fInsert->execute([$fiscalYearId, Uuid::binary16(), $companyId, $fiscalYearId, '2026-03-21', '2027-03-20']);
        }
    }
}
