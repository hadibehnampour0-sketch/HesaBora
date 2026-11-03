<?php

declare(strict_types=1);

namespace Dakhl\Core\Period;

use Dakhl\Cleanroom\Core\Contracts\PeriodFreezeGuardContract;
use Dakhl\Cleanroom\Core\Exceptions\DomainException;
use Dakhl\Core\Support\Uuid;
use PDO;

final class PeriodFreezeService implements PeriodFreezeGuardContract
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function setPeriodStatus(int $companyId, int $fiscalYearId, int $periodNumber, int $status): void
    {
        $this->ensureTenantAndFiscalYear($companyId, $fiscalYearId);

        $stmt = $this->pdo->prepare('
            SELECT id, status
            FROM fiscal_periods
            WHERE company_id = ? AND fiscal_year_id = ? AND period_number = ?
        ');
        $stmt->execute([$companyId, $fiscalYearId, $periodNumber]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $currentStatus = (int) $row['status'];
            if ($status < $currentStatus) {
                throw new DomainException("Illegal backward transition: Period {$periodNumber} cannot transition backwards from status {$currentStatus} to {$status} without formal audit justification.");
            }

            $update = $this->pdo->prepare('
                UPDATE fiscal_periods
                SET status = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ');
            $update->execute([$status, (int) $row['id']]);
        } else {
            $insert = $this->pdo->prepare('
                INSERT INTO fiscal_periods (uuid, company_id, fiscal_year_id, period_number, start_date, end_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $insert->execute([
                Uuid::binary16(),
                $companyId,
                $fiscalYearId,
                $periodNumber,
                '2026-03-21',
                '2026-04-20',
                $status,
            ]);
        }
    }

    public function canPostTransaction(int $companyId, string $date, int $userId, array $userPermissions = []): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT status
            FROM fiscal_periods
            WHERE company_id = ?
            ORDER BY period_number ASC
        ');
        $stmt->execute([$companyId]);
        $periods = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($periods)) {
            return true;
        }

        // Evaluate period status
        foreach ($periods as $period) {
            $status = (int) $period['status'];
            if ($status === 1) {
                continue; // OPEN allows posting
            }

            if ($status === 2) { // SOFT_LOCKED
                if (in_array('period.override', $userPermissions, true) || in_array('*', $userPermissions, true)) {
                    continue;
                }
                return false;
            }

            if ($status >= 3) { // FROZEN, CLOSED, AUDITED
                return false;
            }
        }

        return true;
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
