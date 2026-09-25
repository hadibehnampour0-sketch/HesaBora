<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Phase U: "How is my business doing?" -- plain-language numbers for a period, composed
 * from existing company-scoped data and services (AgeingService, HomeService). Read-only,
 * no new table. Every block is gated by the permission of the full report it summarises.
 *
 * Honesty note: "sales minus purchases" is NOT profit (stock and expenses are not in it);
 * the ledger-based profit is shown separately, fiscal-year-to-date, from the books.
 */
class PlainReportService
{
    public const PERIODS = ['this_month', 'last_month', 'this_year'];

    public function __construct(
        private readonly AgeingService $ageing,
        private readonly HomeService $home,
    ) {}

    /** @return array{0: Carbon, 1: Carbon} inclusive start/end of day */
    public function periodRange(string $period, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();

        if ($period === 'this_year') {
            return Company::withoutGlobalScopes()->findOrFail(getActiveCompany())->fiscalYearRange();
        }

        [$jy, $jm] = gregorian_to_jalali($today->year, $today->month, $today->day);

        if ($period === 'last_month') {
            $jm--;
            if ($jm === 0) {
                $jm = 12;
                $jy--;
            }
        }

        $start = Carbon::create(...jalali_to_gregorian($jy, $jm, 1))->startOfDay();
        $nextY = $jm === 12 ? $jy + 1 : $jy;
        $nextM = $jm === 12 ? 1 : $jm + 1;
        $end = Carbon::create(...jalali_to_gregorian($nextY, $nextM, 1))->subDay()->endOfDay();

        return [$start, $end];
    }

    public function build(User $user, string $period = 'this_month', ?Carbon $today = null): array
    {
        $period = in_array($period, self::PERIODS, true) ? $period : 'this_month';
        [$from, $to] = $this->periodRange($period, $today);

        $data = ['period' => $period, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'blocks' => []];

        if ($user->can('invoices.index')) {
            $data['blocks']['trade'] = $this->tradeBlock($from, $to);
        }

        if ($user->can('ageing.receivables')) {
            $data['blocks']['owed_to_me'] = (float) $this->ageing->report(InvoiceType::SELL)['total'];
        }

        if ($user->can('ageing.payables')) {
            $data['blocks']['i_owe'] = (float) $this->ageing->report(InvoiceType::BUY)['total'];
        }

        if ($user->can('products.index')) {
            $data['blocks']['stock_value'] = $this->home->totalWarehouseValue();
        }

        if ($user->can('documents.show')) {
            $books = $this->home->profitFromNonPermanentSubjects();
            $data['blocks']['books'] = [
                'profit' => (float) $books['profit'],
                'expenses' => (float) array_sum($books['costData']),
            ];
        }

        return $data;
    }

    private function tradeBlock(Carbon $from, Carbon $to): array
    {
        $invoices = Invoice::query()
            ->whereIn('invoice_type', [InvoiceType::SELL, InvoiceType::BUY])
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->whereBetween('date', [$from, $to])
            ->with(['customer:id,name', 'items' => fn ($q) => $q->where('itemable_type', Product::class)])
            ->get();

        $sell = ['count' => 0, 'amount' => 0.0];
        $buy = ['count' => 0, 'amount' => 0.0];
        $customers = [];
        $products = [];

        foreach ($invoices as $invoice) {
            $amount = (float) $invoice->amount;

            if ($invoice->invoice_type === InvoiceType::BUY) {
                $buy['count']++;
                $buy['amount'] += $amount;

                continue;
            }

            $sell['count']++;
            $sell['amount'] += $amount;

            $name = $invoice->customer?->name ?? __('Unknown');
            $customers[$name] = ($customers[$name] ?? 0.0) + $amount;

            foreach ($invoice->items as $item) {
                $products[$item->itemable_id]['amount'] = ($products[$item->itemable_id]['amount'] ?? 0.0) + (float) $item->unit_price * (float) $item->quantity;
                $products[$item->itemable_id]['quantity'] = ($products[$item->itemable_id]['quantity'] ?? 0.0) + (float) $item->quantity;
            }
        }

        arsort($customers);
        uasort($products, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        $topProducts = [];
        foreach (array_slice($products, 0, 5, true) as $productId => $row) {
            $topProducts[] = [
                'name' => Product::withoutGlobalScopes()->whereKey($productId)->value('name') ?? '#'.$productId,
                'amount' => $row['amount'],
                'quantity' => $row['quantity'],
            ];
        }

        return [
            'sell' => $sell,
            'buy' => $buy,
            'difference' => $sell['amount'] - $buy['amount'],
            'top_customers' => collect($customers)->take(5)->map(fn ($amount, $name) => ['name' => (string) $name, 'amount' => $amount])->values()->all(),
            'top_products' => $topProducts,
        ];
    }
}
