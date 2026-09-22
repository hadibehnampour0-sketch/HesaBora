<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Generalizes the AR ageing bucket style already used by CrmDashboardService
 * (0-30/31-60/61-90/Over 90 days) for both receivables (SELL invoices) and
 * payables (BUY invoices), using the precise per-invoice remaining balance
 * from PaymentService instead of an aggregate ledger-credit approximation.
 */
class AgeingService
{
    private const AGING_BUCKETS = [30, 60, 90];

    public function __construct(private readonly PaymentService $paymentService) {}

    /**
     * @return array{buckets: array, parties: array, total: float}
     */
    public function report(InvoiceType $type): array
    {
        $invoiceTypes = $type->isSell() ? [InvoiceType::SELL] : [InvoiceType::BUY];

        $invoices = Invoice::query()
            ->whereIn('invoice_type', $invoiceTypes)
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->with('customer:id,name')
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $buckets = $this->emptyBuckets();
        $parties = [];
        $total = 0.0;
        $today = Carbon::today();

        foreach ($invoices as $invoice) {
            $remaining = $this->paymentService->remainingAmount($invoice);
            if ($remaining <= 0.005) {
                continue;
            }

            $ageDays = $invoice->date->diffInDays($today);
            $bucketIndex = $this->bucketIndex($ageDays);

            $buckets[$bucketIndex]['amount'] += $remaining;
            $total += $remaining;

            $customerId = $invoice->customer_id;
            if (! isset($parties[$customerId])) {
                $parties[$customerId] = [
                    'customer_id' => $customerId,
                    'name' => $invoice->customer?->name ?? __('Unknown'),
                    'total' => 0.0,
                    'buckets' => $this->emptyBuckets(),
                    'invoices' => [],
                ];
            }

            $parties[$customerId]['total'] += $remaining;
            $parties[$customerId]['buckets'][$bucketIndex]['amount'] += $remaining;
            $parties[$customerId]['invoices'][] = [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'date' => $invoice->date,
                'age_days' => $ageDays,
                'amount' => (float) $invoice->amount,
                'remaining' => $remaining,
            ];
        }

        usort($parties, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'buckets' => $buckets,
            'parties' => array_values($parties),
            'total' => $total,
        ];
    }

    /**
     * Flat overdue worklist (age beyond the first bucket), oldest first.
     *
     * @return array<int, array>
     */
    public function overdueWorklist(InvoiceType $type): array
    {
        $report = $this->report($type);
        $overdue = [];

        foreach ($report['parties'] as $party) {
            foreach ($party['invoices'] as $invoice) {
                if ($invoice['age_days'] > self::AGING_BUCKETS[0]) {
                    $overdue[] = [
                        ...$invoice,
                        'customer_id' => $party['customer_id'],
                        'name' => $party['name'],
                    ];
                }
            }
        }

        usort($overdue, fn ($a, $b) => $b['age_days'] <=> $a['age_days']);

        return $overdue;
    }

    /**
     * Chronological invoice statement for one customer/supplier (drill-down),
     * with a running open-balance total.
     *
     * @return array<int, array>
     */
    public function statement(Customer $party, InvoiceType $type): array
    {
        $invoiceTypes = $type->isSell() ? [InvoiceType::SELL, InvoiceType::RETURN_SELL] : [InvoiceType::BUY, InvoiceType::RETURN_BUY];

        $invoices = Invoice::query()
            ->where('customer_id', $party->id)
            ->whereIn('invoice_type', $invoiceTypes)
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $running = 0.0;
        $lines = [];

        foreach ($invoices as $invoice) {
            $remaining = $this->paymentService->remainingAmount($invoice);
            $paid = $this->paymentService->paidAmount($invoice);
            $running += $remaining;

            $lines[] = [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'date' => $invoice->date,
                'invoice_type' => $invoice->invoice_type,
                'amount' => (float) $invoice->amount,
                'paid' => $paid,
                'remaining' => $remaining,
                'running_balance' => $running,
            ];
        }

        return $lines;
    }

    private function emptyBuckets(): array
    {
        return [
            ['label' => __('0-30 days'), 'amount' => 0.0],
            ['label' => __('31-60 days'), 'amount' => 0.0],
            ['label' => __('61-90 days'), 'amount' => 0.0],
            ['label' => __('Over 90 days'), 'amount' => 0.0],
        ];
    }

    private function bucketIndex(int $ageDays): int
    {
        foreach (self::AGING_BUCKETS as $index => $upperBound) {
            if ($ageDays <= $upperBound) {
                return $index;
            }
        }

        return count(self::AGING_BUCKETS);
    }
}
