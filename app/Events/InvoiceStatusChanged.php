<?php

namespace App\Events;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * P6 (API v1 Platform). Fired by InvoiceService::changeInvoiceStatus()
 * after its transaction commits, and only when the status genuinely
 * changed (never for an idempotent no-op re-approve/re-reject). Consumed
 * by WebhookEventSubscriber to feed the webhook platform's
 * `invoice.status_changed` event type; not otherwise used by the
 * application today.
 */
class InvoiceStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly InvoiceStatus $previousStatus,
        public readonly InvoiceStatus $newStatus,
    ) {}
}
