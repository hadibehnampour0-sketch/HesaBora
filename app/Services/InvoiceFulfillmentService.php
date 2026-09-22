<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Models\DeliveryNote;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * P4.1/P4.2 (Purchase Receipt / Sales Delivery Workflow) -- audit-trail
 * confirmation logs only, per the owner's explicit "separate additive
 * layer" choice. Recording a receipt/delivery does NOT move stock; the
 * existing Invoice-approval-driven stock movement (ProductService) is
 * unchanged and remains the sole source of truth for actual quantities.
 */
class InvoiceFulfillmentService
{
    public function recordGoodsReceipt(Invoice $invoice, array $data, User $user): GoodsReceipt
    {
        if ($invoice->invoice_type !== InvoiceType::BUY) {
            throw ValidationException::withMessages([
                'invoice' => [__('A goods receipt can only be recorded for a purchase (BUY) invoice.')],
            ]);
        }

        if (GoodsReceipt::where('invoice_id', $invoice->id)->exists()) {
            throw ValidationException::withMessages([
                'invoice' => [__('A goods receipt has already been recorded for this invoice.')],
            ]);
        }

        return GoodsReceipt::create([
            'invoice_id' => $invoice->id,
            'received_date' => $data['received_date'] ?? now()->toDateString(),
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'received_by' => $user->id,
        ]);
    }

    public function recordDelivery(Invoice $invoice, array $data, User $user): DeliveryNote
    {
        if ($invoice->invoice_type !== InvoiceType::SELL) {
            throw ValidationException::withMessages([
                'invoice' => [__('A delivery note can only be recorded for a sales (SELL) invoice.')],
            ]);
        }

        if (DeliveryNote::where('invoice_id', $invoice->id)->exists()) {
            throw ValidationException::withMessages([
                'invoice' => [__('A delivery note has already been recorded for this invoice.')],
            ]);
        }

        return DeliveryNote::create([
            'invoice_id' => $invoice->id,
            'delivered_date' => $data['delivered_date'] ?? now()->toDateString(),
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'delivered_by' => $user->id,
        ]);
    }
}
