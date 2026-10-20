<?php

namespace App\Services\Pos;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class PosInventoryLockService
{
    protected string $prefix = 'pos_lock';

    protected function makeKey(int $companyId, int $warehouseId, int $productId): string
    {
        return "{$this->prefix}:{$companyId}:{$warehouseId}:{$productId}";
    }

    public function getReservedStock(int $companyId, int $warehouseId, int $productId, ?string $excludeTerminal = null): float
    {
        $key = $this->makeKey($companyId, $warehouseId, $productId);
        $reservations = Cache::get($key, []);

        $sum = 0.0;
        foreach ($reservations as $termId => $info) {
            if ($excludeTerminal && $termId === $excludeTerminal) {
                continue;
            }
            if (($info['expires_at'] ?? 0) > time()) {
                $sum += (float) ($info['quantity'] ?? 0);
            }
        }

        return $sum;
    }

    public function getAvailableStock(int $companyId, int $warehouseId, int $productId, ?string $forTerminal = null): float
    {
        $product = Product::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->find($productId);

        if (! $product) {
            return 0.0;
        }

        $physicalStock = (float) $product->quantity;
        $reservedByOthers = $this->getReservedStock($companyId, $warehouseId, $productId, $forTerminal);

        return max(0.0, $physicalStock - $reservedByOthers);
    }

    public function reserveStock(
        int $companyId,
        int $warehouseId,
        int $productId,
        float $quantity,
        string $terminalId,
        int $ttlSeconds = 300
    ): bool {
        $available = $this->getAvailableStock($companyId, $warehouseId, $productId, $terminalId);

        if ($quantity > $available) {
            return false;
        }

        $key = $this->makeKey($companyId, $warehouseId, $productId);
        $reservations = Cache::get($key, []);

        // Clean expired
        foreach ($reservations as $tId => $info) {
            if (($info['expires_at'] ?? 0) <= time()) {
                unset($reservations[$tId]);
            }
        }

        $reservations[$terminalId] = [
            'quantity' => $quantity,
            'expires_at' => time() + $ttlSeconds,
            'terminal_id' => $terminalId,
        ];

        Cache::put($key, $reservations, $ttlSeconds);
        return true;
    }

    public function releaseReservation(int $companyId, int $warehouseId, int $productId, string $terminalId): void
    {
        $key = $this->makeKey($companyId, $warehouseId, $productId);
        $reservations = Cache::get($key, []);

        if (isset($reservations[$terminalId])) {
            unset($reservations[$terminalId]);
            Cache::put($key, $reservations, 300);
        }
    }

    public function commitReservation(int $companyId, int $warehouseId, int $productId, string $terminalId): void
    {
        // On invoice checkout completion, release the reservation lock as the actual DB stock is decremented
        $this->releaseReservation($companyId, $warehouseId, $productId, $terminalId);
    }

    public function releaseAllTerminalReservations(int $companyId, string $terminalId): void
    {
        // Release any reservations by this terminal across products
        // Standard cache tagging or cleanup can be executed
    }
}
