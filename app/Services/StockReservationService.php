<?php

namespace App\Services;

use App\Enums\StockReservationStatus;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P4.3 (Stock Reservations) -- a separate additive layer, per the owner's
 * explicit choice: this NEVER touches WarehouseProductStock.quantity (the
 * single source of truth for real, on-hand inventory) or
 * ProductService::addProductsQuantities()/subProductsQuantities() (which
 * continue to run exactly as before, unchanged, at invoice approval).
 * "Available" quantity is a purely derived, informational number
 * (on-hand minus the sum of ACTIVE reservations); nothing in the existing
 * Invoice/ProductService flow reads or is gated by a reservation.
 */
class StockReservationService
{
    public function onHandQuantity(Product $product, Warehouse $warehouse): float
    {
        return (float) (WarehouseProductStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->value('quantity') ?? 0.0);
    }

    public function reservedQuantity(Product $product, Warehouse $warehouse): float
    {
        return (float) StockReservation::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('status', StockReservationStatus::ACTIVE)
            ->sum('quantity');
    }

    public function availableQuantity(Product $product, Warehouse $warehouse): float
    {
        return max($this->onHandQuantity($product, $warehouse) - $this->reservedQuantity($product, $warehouse), 0.0);
    }

    /**
     * Concurrency: locks the WarehouseProductStock row (the same row
     * ProductService already locks when actually moving stock) before
     * checking/writing, so two concurrent reservation requests for the
     * SAME product/warehouse cannot both pass the availability check and
     * jointly over-reserve beyond what is actually on hand.
     */
    public function reserve(Product $product, Warehouse $warehouse, float $quantity, ?Model $source, User $user): StockReservation
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => [__('Reservation quantity must be greater than zero.')],
            ]);
        }

        return DB::transaction(function () use ($product, $warehouse, $quantity, $source, $user) {
            WarehouseProductStock::query()
                ->where('product_id', $product->id)
                ->where('warehouse_id', $warehouse->id)
                ->lockForUpdate()
                ->first();

            if ($quantity > $this->availableQuantity($product, $warehouse) + 0.0001) {
                throw ValidationException::withMessages([
                    'quantity' => [__('Reservation quantity exceeds the available (unreserved) stock.')],
                ]);
            }

            return StockReservation::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'quantity' => $quantity,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'created_by' => $user->id,
            ]);
        });
    }

    public function release(StockReservation $reservation): StockReservation
    {
        $this->transitionIfActive($reservation, StockReservationStatus::RELEASED);

        return $reservation->fresh();
    }

    public function consume(StockReservation $reservation): StockReservation
    {
        $this->transitionIfActive($reservation, StockReservationStatus::CONSUMED);

        return $reservation->fresh();
    }

    private function transitionIfActive(StockReservation $reservation, StockReservationStatus $to): void
    {
        if ($reservation->status !== StockReservationStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'reservation' => [__('Only an active reservation can be released or consumed.')],
            ]);
        }

        $reservation->update(['status' => $to]);
    }
}
