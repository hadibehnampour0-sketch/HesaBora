<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\Sms\SmsService;

class PaymentSmsObserver
{
    public function created(Payment $payment): void
    {
        // Only notify if payer exists and has phone
        $payer = $payment->payer ?: Customer::find($payment->payer_id);
        $recipient = $payer?->mobile ?: $payer?->phone;
        if (!$recipient) {
            return;
        }

        // Avoid sending notification for zero or negative amount
        if ((float) $payment->amount <= 0) {
            return;
        }

        $tokens = [
            'customer_name' => $payer->name ?? '',
            'amount' => (string) $payment->amount,
            'date' => $payment->date?->format('Y/m/d') ?? now()->toDateString(),
            'reference_number' => (string) ($payment->reference_number ?? ''),
        ];

        $companyId = $payment->resolvedCompanyId() ?? $payer?->company_id ?? getActiveCompany();
        if (!$companyId) {
            return;
        }

        app(SmsService::class)->sendEventSms(
            eventType: 'payment_received',
            recipient: $recipient,
            tokens: $tokens,
            companyId: (int) $companyId
        );
    }
}
