<?php

declare(strict_types=1);

namespace Dakhl\Core\Bridge;

use App\Models\Invoice;
use Dakhl\Cleanroom\Core\Contracts\CommercialInvoiceLifecycleContract;
use Dakhl\Cleanroom\Core\Contracts\CommercialInvoicePostingContract;
use Dakhl\Cleanroom\Core\Contracts\JournalVoucherPostingContract;
use Dakhl\Cleanroom\Core\Exceptions\InvoiceMutationForbiddenException;
use Illuminate\Validation\ValidationException;

final class CleanroomInvoiceBridge
{
    public function __construct(
        private CommercialInvoicePostingContract $postingContract,
        private CommercialInvoiceLifecycleContract $lifecycleContract,
        private JournalVoucherPostingContract $voucherPostingContract
    ) {}

    /**
     * Enforce commercial invoice mutation boundary.
     *
     * @throws ValidationException
     */
    public function assertCanMutate(Invoice $invoice, string $action = 'edit'): void
    {
        try {
            $this->lifecycleContract->assertCanMutate((int) $invoice->id, $action);
        } catch (InvoiceMutationForbiddenException $e) {
            throw ValidationException::withMessages([
                'invoice' => [__('This invoice has posted payments or allocations and its financial content cannot be changed.')],
            ]);
        }
    }

    /**
     * Check whether invoice can be safely mutated.
     */
    public function canMutate(Invoice $invoice, string $action = 'edit'): bool
    {
        return $this->lifecycleContract->canMutate((int) $invoice->id, $action);
    }

    /**
     * Check whether invoice has active financial activity.
     */
    public function hasFinancialActivity(Invoice $invoice): bool
    {
        return $this->lifecycleContract->hasEffectiveFinancialActivity((int) $invoice->id);
    }

    /**
     * Post commercial invoice through the clean-room double-entry engine.
     *
     * @param  array<int, array{subject_id: int|null, desc: string, value: float}>  $transactions
     * @return int Created Journal Voucher ID
     */
    public function postInvoice(Invoice $invoice, int $userId, array $transactions = []): int
    {
        if (! empty($transactions)) {
            $companyId = (int) ($invoice->company_id ?? 1);
            $fiscalYearId = (int) (session('fiscal_year_id') ?? 1405);
            $voucherDate = $invoice->date ?? date('Y-m-d');
            $description = 'سند حسابداری خودکار فاکتور فروش شماره '.($invoice->number ?? $invoice->id);

            $lines = [];
            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($transactions as $t) {
                $val = (float) ($t['value'] ?? 0);
                if (abs($val) < 0.0001) {
                    continue;
                }

                $subjectId = (int) ($t['subject_id'] ?? 1);
                if ($subjectId <= 0) {
                    $subjectId = 1;
                }

                $desc = (string) ($t['desc'] ?? $description);

                if ($val < 0) {
                    $debit = (int) round(abs($val));
                    $credit = 0;
                    $totalDebit += $debit;
                } else {
                    $debit = 0;
                    $credit = (int) round($val);
                    $totalCredit += $credit;
                }

                $lines[] = [
                    'account_id' => $subjectId,
                    'debit_amount' => $debit,
                    'credit_amount' => $credit,
                    'description' => $desc,
                ];
            }

            // Ensure mathematical balance if rounding difference exists
            if (! empty($lines) && $totalDebit !== $totalCredit) {
                $diff = $totalDebit - $totalCredit;
                foreach ($lines as &$line) {
                    if ($diff > 0 && $line['credit_amount'] > 0) {
                        $line['credit_amount'] += $diff;
                        break;
                    } elseif ($diff < 0 && $line['debit_amount'] > 0) {
                        $line['debit_amount'] += abs($diff);
                        break;
                    }
                }
                unset($line);
            }

            if (! empty($lines)) {
                $voucherId = $this->voucherPostingContract->createDraft(
                    companyId: $companyId,
                    fiscalYearId: $fiscalYearId,
                    voucherDate: (string) $voucherDate,
                    description: $description,
                    lines: $lines,
                    userId: $userId
                );
                $this->voucherPostingContract->postVoucher($voucherId, $userId);

                return $voucherId;
            }
        }

        $revenueAccountId = (int) (config('amir.sales_revenue') ?? 401);
        $vatPayableAccountId = (int) (config('amir.sell_vat') ?? 205);

        return $this->postingContract->postInvoice((int) $invoice->id, $revenueAccountId, $vatPayableAccountId, $userId);
    }
}
