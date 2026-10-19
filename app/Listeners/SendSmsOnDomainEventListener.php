<?php

namespace App\Listeners;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Events\InvoiceStatusChanged;
use App\Services\Sms\SmsService;

class SendSmsOnDomainEventListener
{
    public function __construct(
        protected readonly SmsService $smsService
    ) {}

    public function handleInvoiceStatusChanged(InvoiceStatusChanged $event): void
    {
        $invoice = $event->invoice;

        // Trigger SMS notification only when an invoice genuinely becomes Approved
        if ($event->newStatus !== InvoiceStatus::APPROVED) {
            return;
        }

        // Target sell invoices for customer notification
        if ($invoice->invoice_type !== InvoiceType::SELL) {
            return;
        }

        $customer = $invoice->customer;
        $recipient = $customer?->mobile ?: $customer?->phone;
        if (!$recipient) {
            return;
        }

        $tokens = [
            'customer_name' => $customer->name ?? '',
            'invoice_number' => (string) ($invoice->number ?? $invoice->id),
            'amount' => (string) ($invoice->final_amount ?? $invoice->amount ?? 0),
            'date' => (string) ($invoice->date ?? now()->toDateString()),
        ];

        $this->smsService->sendEventSms(
            eventType: 'invoice_approved',
            recipient: $recipient,
            tokens: $tokens,
            companyId: $invoice->company_id
        );
    }
}
