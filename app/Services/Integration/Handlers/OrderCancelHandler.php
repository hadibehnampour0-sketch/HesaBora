<?php

namespace App\Services\Integration\Handlers;

use App\Models\IntegrationConnection;
use App\Models\Invoice;
use App\Services\Integration\CanonicalEvent;
use App\Services\Integration\ExternalReferenceService;
use App\Services\Integration\HandlerResult;
use App\Services\Integration\IntegrationEventHandler;
use App\Services\Integration\IntegrationEventType;
use App\Services\InvoiceService;

/**
 * Dakhl_INTEGRATION_PLATFORMS_AND_POLICY_PLAN_2026-09-28.md: a cancelled order voids the
 * invoice OrderImportHandler created for it, through the same guarded `InvoiceService::
 * voidInvoice()`/`validateVoidingInvoice()` path the invoice page itself uses -- if that
 * invoice was already paid or is otherwise not voidable, the validation's own rejection reason
 * is surfaced in the review queue rather than the cancellation being silently dropped or forced
 * through. Deliberately does NOT attempt to interpret a refund (REFUND_ISSUED has no handler
 * registered): a partial refund needs to know exactly which lines/amounts were refunded, which
 * this pass does not guess at -- it stays visible as an unhandled event for a human instead.
 */
class OrderCancelHandler implements IntegrationEventHandler
{
    public function __construct(
        private readonly ExternalReferenceService $externalReferenceService,
        private readonly InvoiceService $invoiceService,
    ) {}

    public function handle(CanonicalEvent $event): HandlerResult
    {
        if ($event->type !== IntegrationEventType::ORDER_CANCELLED) {
            return HandlerResult::rejected(__('This handler only processes order-cancelled events.'));
        }

        $invoiceId = $this->externalReferenceService->resolve($event->source, $event->externalId, 'invoice');
        if (! $invoiceId) {
            return HandlerResult::pendingDependency(__('No Dakhl invoice has been imported for this order yet.'));
        }

        $invoice = Invoice::find($invoiceId);
        if (! $invoice) {
            return HandlerResult::pendingDependency(__('The invoice imported for this order no longer exists.'));
        }

        $decision = $this->invoiceService->validateVoidingInvoice($invoice);
        if ($decision->hasErrors()) {
            return HandlerResult::pendingDependency(implode(' ', $decision->messages->pluck('text')->all()));
        }

        $connection = IntegrationConnection::where('source_system', $event->source)->where('is_active', true)->first();
        $actingUser = $connection?->creator ?? $connection?->company?->users()->first();
        if (! $actingUser) {
            return HandlerResult::pendingDependency(__('No active connection configured for :source.', ['source' => $event->source]));
        }

        $number = (float) ((Invoice::withoutGlobalScopes()->max('number') ?? 0) + 1);
        $this->invoiceService->voidInvoice($invoice, $actingUser, now()->toDateString(), $number);

        return HandlerResult::processed(__('Voided invoice #:number', ['number' => $invoice->number]));
    }
}
