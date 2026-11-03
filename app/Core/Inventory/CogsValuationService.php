<?php

declare(strict_types=1);

namespace Dakhl\Core\Inventory;

use Dakhl\Cleanroom\Core\Contracts\InventoryValuationCogsContract;
use Dakhl\Cleanroom\Core\Exceptions\InsufficientInventoryException;
use Dakhl\Core\Support\Uuid;
use PDO;

final class CogsValuationService implements InventoryValuationCogsContract
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function recordInbound(
        int $companyId,
        string $itemCode,
        string $quantity,
        int $totalCost,
        ?string $uuid = null
    ): int {
        $this->ensureTenantExists($companyId);
        $qtyIn = (float) $quantity;

        // Fetch current stock
        [$currentQty, $currentVal] = $this->getCurrentStock($companyId, $itemCode);

        $newQty = $currentQty + $qtyIn;
        $newVal = $currentVal + $totalCost;
        $avgCost = $newQty > 0 ? (int) round($newVal / $newQty, 0, PHP_ROUND_HALF_UP) : 0;
        $unitCost = $qtyIn > 0 ? (int) round($totalCost / $qtyIn, 0, PHP_ROUND_HALF_UP) : 0;

        $binaryUuid = Uuid::binary16($uuid);
        $stmt = $this->pdo->prepare('
            INSERT INTO stock_movements (
                uuid, company_id, item_code, movement_type, movement_date,
                quantity, unit_cost, total_cost, remaining_quantity, moving_average_cost
            ) VALUES (?, ?, ?, 1, CURRENT_DATE, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $binaryUuid,
            $companyId,
            $itemCode,
            $qtyIn,
            $unitCost,
            $totalCost,
            $newQty,
            $avgCost,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function calculateCogs(
        int $companyId,
        string $itemCode,
        string $quantity,
        bool $allowNegativeStock = false
    ): int {
        $qtyOut = (float) $quantity;
        [$currentQty, $currentVal] = $this->getCurrentStock($companyId, $itemCode);

        if ($qtyOut > $currentQty && ! $allowNegativeStock) {
            throw new InsufficientInventoryException("Insufficient inventory for item {$itemCode}: requested {$qtyOut}, available {$currentQty}.");
        }

        if ($currentQty <= 0) {
            return 0;
        }

        $unitRate = $currentVal / $currentQty;
        $cogs = (int) round($qtyOut * $unitRate, 0, PHP_ROUND_HALF_UP);

        $newQty = max(0.0, $currentQty - $qtyOut);
        $newVal = max(0, $currentVal - $cogs);
        $avgCost = $newQty > 0 ? (int) round($newVal / $newQty, 0, PHP_ROUND_HALF_UP) : (int) round($unitRate, 0, PHP_ROUND_HALF_UP);

        $stmt = $this->pdo->prepare('
            INSERT INTO stock_movements (
                uuid, company_id, item_code, movement_type, movement_date,
                quantity, unit_cost, total_cost, remaining_quantity, moving_average_cost
            ) VALUES (?, ?, ?, 2, CURRENT_DATE, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            Uuid::binary16(),
            $companyId,
            $itemCode,
            -$qtyOut,
            (int) round($unitRate, 0, PHP_ROUND_HALF_UP),
            $cogs,
            $newQty,
            $avgCost,
        ]);

        return $cogs;
    }

    public function getCurrentAverageUnitCost(int $companyId, string $itemCode): float
    {
        [$currentQty, $currentVal] = $this->getCurrentStock($companyId, $itemCode);

        if ($currentQty <= 0) {
            return 0.0;
        }

        return round($currentVal / $currentQty, 6);
    }

    /**
     * @return array{0: float, 1: int} [remaining_quantity, total_inventory_value]
     */
    private function getCurrentStock(int $companyId, string $itemCode): array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                COALESCE(SUM(quantity), 0) AS total_qty,
                COALESCE(SUM(CASE WHEN movement_type = 1 THEN total_cost ELSE -total_cost END), 0) AS total_val
            FROM stock_movements
            WHERE company_id = ? AND item_code = ?
        ');
        $stmt->execute([$companyId, $itemCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $row) {
            return [0.0, 0];
        }

        $remQty = (float) $row['total_qty'];
        $val = (int) $row['total_val'];

        return [max(0.0, $remQty), max(0, $val)];
    }

    private function ensureTenantExists(int $companyId): void
    {
        $cStmt = $this->pdo->prepare('SELECT id FROM companies WHERE id = ?');
        $cStmt->execute([$companyId]);
        if ($cStmt->fetchColumn() === false) {
            $cInsert = $this->pdo->prepare('INSERT INTO companies (id, uuid, name) VALUES (?, ?, ?)');
            $cInsert->execute([$companyId, Uuid::binary16(), "Company {$companyId}"]);
        }
    }
}
