<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentTerm;
use App\Models\SettlementObligation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentTermService
{
    public function __construct(private readonly SettlementService $settlementService) {}

    /**
     * @param  array<int, array{days_offset: int, percentage: float}>  $lines
     */
    public function create(array $data, array $lines): PaymentTerm
    {
        $this->validateLines($lines);

        return DB::transaction(function () use ($data, $lines) {
            $term = PaymentTerm::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->replaceLines($term, $lines);

            return $term->load('lines');
        });
    }

    /**
     * @param  array<int, array{days_offset: int, percentage: float}>|null  $lines
     */
    public function update(PaymentTerm $term, array $data, ?array $lines = null): PaymentTerm
    {
        return DB::transaction(function () use ($term, $data, $lines) {
            $term->update([
                'code' => $data['code'] ?? $term->code,
                'name' => $data['name'] ?? $term->name,
                'description' => $data['description'] ?? $term->description,
                'is_active' => $data['is_active'] ?? $term->is_active,
            ]);

            if ($lines !== null) {
                $this->validateLines($lines);
                $this->replaceLines($term, $lines);
            }

            return $term->fresh('lines');
        });
    }

    private function validateLines(array $lines): void
    {
        if (empty($lines)) {
            throw ValidationException::withMessages([
                'lines' => [__('A payment term must have at least one schedule line.')],
            ]);
        }

        $total = array_sum(array_map(fn ($line) => (float) $line['percentage'], $lines));
        if (abs($total - 100.0) > 0.01) {
            throw ValidationException::withMessages([
                'lines' => [__('The payment term schedule percentages must sum to exactly 100%.')],
            ]);
        }
    }

    private function replaceLines(PaymentTerm $term, array $lines): void
    {
        $term->lines()->delete();

        foreach ($lines as $index => $line) {
            $term->lines()->create([
                'days_offset' => (int) $line['days_offset'],
                'percentage' => (float) $line['percentage'],
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * Applies a payment term to an invoice: creates a SettlementObligation
     * (source=Invoice, reusing D-070's own generic ledger) and one
     * SettlementInstallment per schedule line, due date = invoice date +
     * days_offset, amount = percentage x the invoice's payable amount.
     */
    public function applyToInvoice(Invoice $invoice, PaymentTerm $term, User $user): SettlementObligation
    {
        $term->loadMissing('lines');

        if ($term->lines->isEmpty()) {
            throw ValidationException::withMessages([
                'payment_term' => [__('This payment term has no schedule lines.')],
            ]);
        }

        return DB::transaction(function () use ($invoice, $term, $user) {
            $obligation = $this->settlementService->createObligationForInvoice($invoice, $user);
            $amount = (float) $obligation->original_amount;

            $installments = $term->lines->map(fn ($line) => [
                'due_date' => $invoice->date->copy()->addDays((int) $line->days_offset)->toDateString(),
                'amount' => round($amount * ((float) $line->percentage / 100), 2),
            ])->all();

            // Rounding across N lines can leave a few cents unassigned --
            // fold any remainder into the LAST installment so the plan's
            // total always exactly equals the obligation, never more or
            // less (createInstallmentPlan() itself would otherwise reject
            // a plan that doesn't sum to the full amount within tolerance
            // due to only allowing UP TO the unscheduled headroom -- a
            // shortfall would silently under-schedule, not error).
            $scheduledTotal = array_sum(array_column($installments, 'amount'));
            $remainder = round($amount - $scheduledTotal, 2);
            if (abs($remainder) > 0.001) {
                $lastIndex = count($installments) - 1;
                $installments[$lastIndex]['amount'] = round($installments[$lastIndex]['amount'] + $remainder, 2);
            }

            $this->settlementService->createInstallmentPlan($obligation, $installments);

            return $obligation->fresh('installments');
        });
    }
}
