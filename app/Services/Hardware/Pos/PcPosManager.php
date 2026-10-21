<?php

namespace App\Services\Hardware\Pos;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PosTerminal;
use App\Models\PosTransaction;
use App\Models\Subject;
use App\Models\User;
use App\Services\Hardware\Pos\Adapters\BehpardakhtMellatAdapter;
use App\Services\Hardware\Pos\Adapters\GenericPspAdapter;
use App\Services\Hardware\Pos\Adapters\SadadAdapter;
use App\Services\Hardware\Pos\Adapters\SamanKishAdapter;
use App\Services\Hardware\Pos\Adapters\SimulatorPosAdapter;
use App\Services\Hardware\Pos\Contracts\PosTerminalAdapterInterface;
use App\Services\PaymentService;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PcPosManager
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Resolve the corresponding protocol adapter for this terminal's PSP.
     */
    public function resolveAdapter(PosTerminal $terminal): PosTerminalAdapterInterface
    {
        return match ($terminal->psp) {
            'simulator' => app(SimulatorPosAdapter::class),
            'behpardakht' => app(BehpardakhtMellatAdapter::class),
            'saman_kish' => app(SamanKishAdapter::class),
            'sadad' => app(SadadAdapter::class),
            default => app(GenericPspAdapter::class),
        };
    }

    /**
     * Test connection to physical/virtual terminal and update status.
     */
    public function ping(PosTerminal $terminal): bool
    {
        $adapter = $this->resolveAdapter($terminal);
        $isOnline = $adapter->ping($terminal);

        $terminal->update([
            'last_ping_at' => now(),
            'last_status' => $isOnline ? 'online' : 'offline',
        ]);

        return $isOnline;
    }

    /**
     * Initiate payment transaction for a sales invoice through PC-POS.
     */
    public function initiateInvoicePayment(
        User $user,
        PosTerminal $terminal,
        Invoice $invoice,
        ?float $amount = null,
        array $options = []
    ): PosTransaction {
        if (! $terminal->is_active) {
            throw new InvalidArgumentException(__('دستگاه کارتخوان انتخاب‌شده غیرفعال است.'));
        }

        $payableAmount = $amount ?? (float) ($this->paymentService->remainingAmount($invoice) ?: ($invoice->remaining_amount ?? $invoice->final_amount));
        if ($payableAmount <= 0) {
            throw new InvalidArgumentException(__('مبلغ تراکنش کارتخوان باید بزرگتر از صفر باشد.'));
        }

        $traceNumber = (string) Str::ulid();

        // 1. Create Pending Transaction Record
        $transaction = PosTransaction::create([
            'company_id' => $terminal->company_id,
            'pos_terminal_id' => $terminal->id,
            'invoice_id' => $invoice->id,
            'user_id' => $user->id,
            'trace_number' => $traceNumber,
            'amount' => $payableAmount,
            'status' => 'pending',
            'raw_payload' => [
                'invoice_number' => $invoice->number,
                'customer_name' => $invoice->customer?->name,
                'options' => $options,
            ],
            'transaction_at' => now(),
        ]);

        // 2. Dispatch to PSP Adapter
        $adapter = $this->resolveAdapter($terminal);
        $result = $adapter->sendPaymentRequest($terminal, $payableAmount, $traceNumber, array_merge($options, [
            'description' => "فاکتور #{$invoice->number}",
        ]));

        // 3. Update Transaction Record
        $transaction->update([
            'status' => $result->status,
            'rrn' => $result->rrn,
            'terminal_trace_no' => $result->terminalTraceNo,
            'card_number_masked' => $result->maskedCardNumber,
            'card_holder_name' => $result->cardHolderName,
            'response_code' => $result->responseCode,
            'response_message' => $result->message,
            'raw_response' => $result->rawResponse,
        ]);

        // 4. Auto-Settle upon successful transaction
        if ($result->isSuccess ?? $result->success) {
            $this->autoSettlePayment($user, $transaction, $invoice);
        }

        return $transaction->fresh(['invoice', 'payment', 'terminal']);
    }

    /**
     * Automatically settle the invoice using PaymentService and link to the POS transaction.
     */
    public function autoSettlePayment(User $user, PosTransaction $transaction, ?Invoice $invoice = null): ?Payment
    {
        $invoice = $invoice ?? $transaction->invoice;
        if (! $invoice) {
            return null;
        }

        $terminal = $transaction->terminal;

        // Resolve Bank Account Subject
        $subjectId = $terminal->bank_account_subject_id;
        if (! $subjectId) {
            $fallbackSubject = Subject::where('company_id', $terminal->company_id)
                ->where(function ($q) {
                    $q->where('name', 'like', '%بانک%')
                        ->orWhere('name', 'like', '%پوز%')
                        ->orWhere('name', 'like', '%کارتخوان%');
                })->first();

            $subjectId = $fallbackSubject?->id;
        }

        if (! $subjectId) {
            // Pick first subject available for this company
            $subjectId = Subject::where('company_id', $terminal->company_id)->value('id');
        }

        if (! $subjectId) {
            return null;
        }

        $rrn = $transaction->rrn ?? $transaction->terminal_trace_no ?? $transaction->trace_number;
        $pan = $transaction->card_number_masked ? " (کارت: {$transaction->card_number_masked})" : '';

        $paymentData = [
            'amount' => $transaction->amount,
            'subject_id' => $subjectId,
            'date' => now()->toDateString(),
            'reference_number' => substr((string) $rrn, 0, 20),
            'description' => "تسویه خودکار کارتخوان {$terminal->name} - پیگیری: {$rrn}{$pan}",
        ];

        // Call the official PaymentService createPayment (guarantees balanced document and locking)
        $decision = $this->paymentService->createPayment($user, $invoice, $paymentData);

        if (! $decision->hasErrors()) {
            $createdPayment = Payment::where('invoice_id', $invoice->id)
                ->where('amount', $transaction->amount)
                ->latest('id')
                ->first();

            if ($createdPayment) {
                $transaction->update(['payment_id' => $createdPayment->id]);

                return $createdPayment;
            }
        }

        return null;
    }
}
