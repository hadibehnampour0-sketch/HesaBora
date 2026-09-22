<?php

namespace App\Services;

use App\Enums\SerialNumberStatus;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\ProductSerialNumber;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P4.5/P4.6 (Lot/Batch tracking, Serial numbers) -- an additive
 * sub-unit-tracking layer, same principle as P4.1-P4.3: never reads or
 * gates ProductService's existing quantity/COGS logic.
 */
class ProductTrackingService
{
    public function receiveLot(Product $product, Warehouse $warehouse, string $lotNumber, float $quantity, string $receivedDate, ?string $expiryDate = null): ProductLot
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => [__('Lot quantity must be greater than zero.')],
            ]);
        }

        if (ProductLot::where('product_id', $product->id)->where('lot_number', $lotNumber)->exists()) {
            throw ValidationException::withMessages([
                'lot_number' => [__('A lot with this number already exists for this product.')],
            ]);
        }

        return ProductLot::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'lot_number' => $lotNumber,
            'quantity' => $quantity,
            'received_date' => $receivedDate,
            'expiry_date' => $expiryDate,
        ]);
    }

    /**
     * Reduces a lot's tracked quantity (e.g. FEFO consumption at sale
     * time). Concurrency: locks the lot row so two concurrent consumptions
     * cannot both pass the "enough left in this lot" check and jointly
     * over-consume it below zero.
     */
    public function consumeFromLot(ProductLot $lot, float $quantity): ProductLot
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => [__('Consumption quantity must be greater than zero.')],
            ]);
        }

        return DB::transaction(function () use ($lot, $quantity) {
            $locked = ProductLot::query()->lockForUpdate()->findOrFail($lot->id);

            if ($quantity > (float) $locked->quantity + 0.0001) {
                throw ValidationException::withMessages([
                    'quantity' => [__('Consumption quantity exceeds the remaining quantity in this lot.')],
                ]);
            }

            $locked->update(['quantity' => (float) $locked->quantity - $quantity]);

            return $locked->fresh();
        });
    }

    /**
     * Earliest-expiry-first ordering (expiry_date nulls last, then oldest
     * expiry, then oldest received) -- the standard FEFO picking order for
     * a caller that wants to consume from lots automatically.
     */
    public function lotsForProduct(Product $product, Warehouse $warehouse)
    {
        return ProductLot::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('quantity', '>', 0)
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->orderBy('received_date')
            ->get();
    }

    public function registerSerial(Product $product, Warehouse $warehouse, string $serialNumber): ProductSerialNumber
    {
        if (ProductSerialNumber::where('serial_number', $serialNumber)->exists()) {
            throw ValidationException::withMessages([
                'serial_number' => [__('This serial number is already registered.')],
            ]);
        }

        return ProductSerialNumber::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'serial_number' => $serialNumber,
        ]);
    }

    public function markSold(ProductSerialNumber $serial, Invoice $invoice): ProductSerialNumber
    {
        if ($serial->status !== SerialNumberStatus::IN_STOCK) {
            throw ValidationException::withMessages([
                'serial_number' => [__('This serial number is not currently in stock.')],
            ]);
        }

        $serial->update(['status' => SerialNumberStatus::SOLD, 'invoice_id' => $invoice->id, 'warehouse_id' => null]);

        return $serial->fresh();
    }

    public function markReturned(ProductSerialNumber $serial, Warehouse $warehouse): ProductSerialNumber
    {
        if ($serial->status !== SerialNumberStatus::SOLD) {
            throw ValidationException::withMessages([
                'serial_number' => [__('This serial number is not currently marked as sold.')],
            ]);
        }

        $serial->update(['status' => SerialNumberStatus::IN_STOCK, 'invoice_id' => null, 'warehouse_id' => $warehouse->id]);

        return $serial->fresh();
    }
}
