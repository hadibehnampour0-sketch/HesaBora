<?php

namespace App\Services\Payment;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGatewayConfig;
use App\Models\PaymentLink;
use App\Models\Subject;
use App\Models\User;
use App\Services\PaymentGateway\Contracts\PaymentGatewayInterface;
use App\Services\PaymentGateway\DTO\PaymentGatewayResponse;
use App\Services\PaymentGateway\DTO\PaymentVerificationResult;
use App\Services\PaymentGateway\Gateways\SandboxGateway;
use App\Services\PaymentGateway\Gateways\ZarinpalGateway;
use App\Services\PaymentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentLinkService
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function generateInvoicePaymentLink(
        Invoice $invoice,
        ?float $amount = null,
        ?string $gateway = null,
        ?User $user = null
    ): PaymentLink {
        $remaining = $this->paymentService->remainingAmount($invoice);
        $amount = $amount ?: $remaining;

        if ($amount <= 0) {
            throw new InvalidArgumentException(__('Payment amount exceeds the remaining balance of the invoice.'));
        }

        $gateway = $gateway ?: $this->resolveDefaultGatewayName($invoice->company_id);

        return PaymentLink::create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'amount' => $amount,
            'gateway' => $gateway,
            'token' => Str::random(64),
            'status' => PaymentLink::STATUS_PENDING,
            'expires_at' => Carbon::now()->addDays(7),
            'created_by' => $user?->id,
        ]);
    }

    public function resolveDefaultGatewayName(int $companyId): string
    {
        $config = PaymentGatewayConfig::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->first();

        return $config?->gateway ?? 'sandbox';
    }

    public function resolveGateway(PaymentLink $link): PaymentGatewayInterface
    {
        $config = PaymentGatewayConfig::withoutGlobalScopes()
            ->where('company_id', $link->company_id)
            ->where('gateway', $link->gateway)
            ->first();

        return match ($link->gateway) {
            'zarinpal' => new ZarinpalGateway(
                merchantId: $config?->merchant_id,
                isSandbox: $config?->is_sandbox ?? false
            ),
            'sandbox', 'log', 'test' => new SandboxGateway,
            default => new SandboxGateway,
        };
    }

    public function initiatePayment(PaymentLink $link, string $callbackUrl): PaymentGatewayResponse
    {
        if ($link->isPaid()) {
            return PaymentGatewayResponse::failure('این لینک پرداخت قبلاً تسویه شده است.');
        }

        if ($link->isExpired()) {
            return PaymentGatewayResponse::failure('مهلت پرداخت این لینک منقضی شده است.');
        }

        $gateway = $this->resolveGateway($link);
        $response = $gateway->requestPayment($link, $callbackUrl);

        if ($response->successful && $response->gatewayToken) {
            $link->update([
                'gateway_token' => $response->gatewayToken,
            ]);
        }

        return $response;
    }

    public function processCallback(
        PaymentLink $link,
        array $callbackData,
        ?User $systemUser = null
    ): PaymentVerificationResult {
        if ($link->isPaid()) {
            return PaymentVerificationResult::success(
                referenceNumber: $link->gateway_reference ?? 'ALREADY_PAID',
                cardPan: $link->card_pan
            );
        }

        if ($link->isExpired()) {
            $link->update(['status' => PaymentLink::STATUS_EXPIRED]);
            return PaymentVerificationResult::failure('مهلت پرداخت این لینک منقضی شده است.');
        }

        $gateway = $this->resolveGateway($link);
        $verification = $gateway->verifyPayment($link, $callbackData);

        if (! $verification->successful) {
            $link->update(['status' => PaymentLink::STATUS_FAILED]);
            return $verification;
        }

        return DB::transaction(function () use ($link, $verification, $systemUser) {
            $invoice = $link->invoice;
            if (! $invoice) {
                return PaymentVerificationResult::failure('فاکتور متصل به این لینک یافت نشد.');
            }

            // Find settlement subject
            $settlementSubjectId = $this->resolveSettlementSubjectId($link->company_id);

            $user = $systemUser ?? $link->creator ?? User::first();
            if (! $user) {
                return PaymentVerificationResult::failure('کاربر ثبت‌کننده معتبر یافت نشد.');
            }

            $decision = $this->paymentService->createPayment($user, $invoice, [
                'amount' => (float) $link->amount,
                'date' => Carbon::now()->toDateString(),
                'subject_id' => $settlementSubjectId,
                'reference_number' => $verification->referenceNumber,
                'description' => "پرداخت اینترنتی فاکتور شماره {$invoice->number} (کد رهگیری: {$verification->referenceNumber})",
            ]);

            if ($decision->hasErrors()) {
                return PaymentVerificationResult::failure(
                    'خطا در ثبت سند حسابداری پرداخت: ' . $decision->messages->pluck('text')->implode(' - ')
                );
            }

            $payment = Payment::withoutGlobalScopes()
                ->where('invoice_id', $invoice->id)
                ->where('reference_number', $verification->referenceNumber)
                ->latest()
                ->first();

            $link->update([
                'status' => PaymentLink::STATUS_PAID,
                'paid_at' => Carbon::now(),
                'gateway_reference' => $verification->referenceNumber,
                'card_pan' => $verification->cardPan,
                'payment_id' => $payment?->id,
            ]);

            return $verification;
        });
    }

    public function resolveSettlementSubjectId(int $companyId): int
    {
        $config = PaymentGatewayConfig::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNotNull('default_subject_id')
            ->first();

        if ($config && $config->default_subject_id) {
            return $config->default_subject_id;
        }

        $subjectIds = $this->paymentService->settlementSubjectIds();
        if (! empty($subjectIds)) {
            return $subjectIds[0];
        }

        // Fallback: check any subject under bank or cashbook
        $bankRootId = (int) config('amir.bank');
        $child = Subject::withoutGlobalScopes()
            ->where('parent_id', $bankRootId)
            ->where('company_id', $companyId)
            ->first();

        if ($child) {
            return $child->id;
        }

        // Return root or first available subject
        return $bankRootId ?: Subject::first()?->id ?: 1;
    }
}
