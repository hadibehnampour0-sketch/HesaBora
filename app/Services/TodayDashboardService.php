<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Phase U "Today" dashboard: a pure read view composed from existing services
 * (AgeingService, ChequeRiskDashboardService) and the company-scoped Invoice
 * model. Introduces no table and no posting path. Every block is gated by the
 * SAME permission that guards the full report it summarises, so the dashboard
 * never reveals more than the user could already open.
 */
class TodayDashboardService
{
    public function __construct(
        private readonly AgeingService $ageing,
        private readonly ChequeRiskDashboardService $cheques,
        private readonly InsightDetectorService $insights,
    ) {}

    public function build(User $user, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $data = ['date' => $today->toDateString(), 'blocks' => []];

        if ($user->can('invoices.index')) {
            $data['blocks']['sales'] = $this->salesBlock($today);
        }

        if ($user->can('ageing.receivables')) {
            $data['blocks']['receivables'] = $this->ageingBlock(InvoiceType::SELL);
        }

        if ($user->can('ageing.payables')) {
            $data['blocks']['payables'] = $this->ageingBlock(InvoiceType::BUY);
        }

        if ($user->can('cheques.index')) {
            $data['blocks']['cheques'] = $this->chequeBlock();
        }

        $insights = $this->insights->detect($user, $today);

        if ($insights !== []) {
            $data['blocks']['insights'] = $insights;
        }

        return $data;
    }

    /** Today's sell/buy totals plus a 7-day sell-vs-buy series. */
    private function salesBlock(Carbon $today): array
    {
        $from = $today->copy()->subDays(6)->startOfDay();

        $invoices = Invoice::query()
            ->whereIn('invoice_type', [InvoiceType::SELL, InvoiceType::BUY])
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->whereBetween('date', [$from, $today->copy()->endOfDay()])
            ->get(['id', 'invoice_type', 'date', 'amount']);

        $series = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $from->copy()->addDays($i);
            $series[$day->toDateString()] = ['date' => $day->toDateString(), 'sell' => 0.0, 'buy' => 0.0];
        }

        $todaySell = ['count' => 0, 'amount' => 0.0];
        $todayBuy = ['count' => 0, 'amount' => 0.0];

        foreach ($invoices as $invoice) {
            $key = $invoice->date->toDateString();
            $isSell = $invoice->invoice_type === InvoiceType::SELL;
            $amount = (float) $invoice->amount;

            if (isset($series[$key])) {
                $series[$key][$isSell ? 'sell' : 'buy'] += $amount;
            }

            if ($key === $today->toDateString()) {
                $bucket = $isSell ? 'todaySell' : 'todayBuy';
                $$bucket['count']++;
                $$bucket['amount'] += $amount;
            }
        }

        return [
            'today_sell' => $todaySell,
            'today_buy' => $todayBuy,
            'series' => array_values($series),
        ];
    }

    /** Open balance split into "current" (first ageing bucket) and overdue, plus the biggest overdue parties. */
    private function ageingBlock(InvoiceType $type): array
    {
        $report = $this->ageing->report($type);
        $current = (float) ($report['buckets'][0]['amount'] ?? 0.0);
        $total = (float) $report['total'];

        $overdueByParty = [];
        foreach ($this->ageing->overdueWorklist($type) as $row) {
            $overdueByParty[$row['customer_id']] ??= ['customer_id' => $row['customer_id'], 'name' => $row['name'], 'remaining' => 0.0, 'oldest_days' => 0];
            $overdueByParty[$row['customer_id']]['remaining'] += (float) $row['remaining'];
            $overdueByParty[$row['customer_id']]['oldest_days'] = max($overdueByParty[$row['customer_id']]['oldest_days'], (int) $row['age_days']);
        }

        $top = collect($overdueByParty)->sortByDesc('remaining')->take(5)->values()->all();

        return [
            'total' => $total,
            'current' => $current,
            'overdue' => max(0.0, $total - $current),
            'top_overdue' => $top,
            'type' => $type->isSell() ? 'sell' : 'buy',
        ];
    }

    private function chequeBlock(): array
    {
        $report = $this->cheques->report(7);

        $summary = fn (Collection $items) => ['count' => $items->count(), 'amount' => round((float) $items->sum('amount'), 2)];

        return [
            'receivable' => [
                'due_soon' => $summary($report['receivable']['due_soon']),
                'overdue' => $summary($report['receivable']['overdue']),
                'bounced' => $summary($report['receivable']['bounced']),
            ],
            'payable' => [
                'due_soon' => $summary($report['payable']['due_soon']),
                'overdue' => $summary($report['payable']['overdue']),
            ],
        ];
    }
}
