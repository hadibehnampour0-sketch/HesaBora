<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Phase AI, tier 1: DETERMINISTIC business insights. No provider, no LLM, no
 * persistence -- pure read-only rules over existing, company-scoped data and
 * existing report services, evaluated on demand for the "Needs attention"
 * list. Each detector is gated by the permission of the report it derives
 * from (same rule as TodayDashboardService), so an insight never reveals more
 * than the user could already open. Thresholds are class constants for now;
 * making them per-company settings is a planned follow-up (see design doc A6).
 *
 * Insight shape: type, severity (info|warning|error), title, detail, url.
 */
class InsightDetectorService
{
    /** Two same-customer, same-type, same-amount invoices this many days apart or closer are "possible duplicates". */
    public const DUPLICATE_WINDOW_DAYS = 1;

    public const DUPLICATE_LOOKBACK_DAYS = 30;

    /** A recent buy price this many times the trailing average is flagged. */
    public const PRICE_SPIKE_RATIO = 1.25;

    public const PRICE_RECENT_DAYS = 30;

    public const PRICE_BASELINE_DAYS = 90;

    /** One customer holding at least this share of open receivables (with 3+ debtors) is a concentration risk. */
    public const CONCENTRATION_SHARE = 0.40;

    public const CONCENTRATION_MIN_PARTIES = 3;

    public function __construct(
        private readonly AgeingService $ageing,
        private readonly ChequeRiskDashboardService $cheques,
    ) {}

    /** @return list<array{type: string, severity: string, title: string, detail: string, url: string}> */
    public function detect(User $user, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $insights = [];

        if ($user->can('invoices.index')) {
            array_push($insights, ...$this->possibleDuplicateInvoices($today), ...$this->buyPriceSpikes($today));
        }

        if ($user->can('ageing.receivables')) {
            array_push($insights, ...$this->receivableConcentration());
        }

        if ($user->can('cheques.index')) {
            array_push($insights, ...$this->bouncedCheques());
        }

        $rank = ['error' => 0, 'warning' => 1, 'info' => 2];
        usort($insights, fn ($a, $b) => $rank[$a['severity']] <=> $rank[$b['severity']]);

        return $insights;
    }

    /** @return list<array> */
    private function possibleDuplicateInvoices(Carbon $today): array
    {
        $invoices = Invoice::query()
            ->whereIn('invoice_type', [InvoiceType::SELL, InvoiceType::BUY])
            ->whereDate('date', '>=', $today->copy()->subDays(self::DUPLICATE_LOOKBACK_DAYS))
            ->orderBy('date')
            ->get(['id', 'number', 'customer_id', 'invoice_type', 'date', 'amount']);

        $groups = $invoices
            ->filter(fn (Invoice $i) => $i->customer_id !== null && (float) $i->amount > 0)
            ->groupBy(fn (Invoice $i) => $i->customer_id.'|'.$i->invoice_type->value.'|'.round((float) $i->amount, 2));

        $out = [];

        foreach ($groups as $group) {
            $sorted = $group->sortBy('date')->values();

            for ($i = 1; $i < $sorted->count(); $i++) {
                $previous = $sorted[$i - 1];
                $current = $sorted[$i];

                if ($previous->date->diffInDays($current->date) <= self::DUPLICATE_WINDOW_DAYS) {
                    $out[] = [
                        'type' => 'possible_duplicate_invoice',
                        'severity' => 'warning',
                        'title' => __('Possible duplicate invoice'),
                        'detail' => __('Invoices :a and :b have the same customer, type and amount within :days day(s).', [
                            'a' => localizeNumber($previous->number),
                            'b' => localizeNumber($current->number),
                            'days' => localizeNumber(self::DUPLICATE_WINDOW_DAYS),
                        ]),
                        'url' => route('invoices.show', $current),
                    ];
                }
            }
        }

        return $out;
    }

    /** @return list<array> */
    private function buyPriceSpikes(Carbon $today): array
    {
        $recentFrom = $today->copy()->subDays(self::PRICE_RECENT_DAYS)->startOfDay();
        $baselineFrom = $today->copy()->subDays(self::PRICE_RECENT_DAYS + self::PRICE_BASELINE_DAYS)->startOfDay();

        $invoices = Invoice::query()
            ->where('invoice_type', InvoiceType::BUY)
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->whereBetween('date', [$baselineFrom, $today->copy()->endOfDay()])
            ->with(['items' => fn ($q) => $q->where('itemable_type', Product::class)])
            ->get();

        $baseline = [];
        $recent = [];

        foreach ($invoices as $invoice) {
            $isRecent = $invoice->date->gte($recentFrom);

            foreach ($invoice->items as $item) {
                if ((float) $item->unit_price <= 0) {
                    continue;
                }

                $isRecent
                    ? $recent[$item->itemable_id][] = ['price' => (float) $item->unit_price, 'invoice' => $invoice]
                    : $baseline[$item->itemable_id][] = (float) $item->unit_price;
            }
        }

        $out = [];

        foreach ($recent as $productId => $entries) {
            $history = $baseline[$productId] ?? [];

            if (count($history) < 2) {
                continue;
            }

            $average = array_sum($history) / count($history);
            $latest = end($entries);

            if ($latest['price'] >= $average * self::PRICE_SPIKE_RATIO) {
                $name = Product::withoutGlobalScopes()->whereKey($productId)->value('name') ?? '#'.$productId;

                $out[] = [
                    'type' => 'buy_price_spike',
                    'severity' => 'warning',
                    'title' => __('Purchase price increase'),
                    'detail' => __(':product was bought :percent% above its recent average price.', [
                        'product' => $name,
                        'percent' => localizeNumber((string) round(($latest['price'] / $average - 1) * 100)),
                    ]),
                    'url' => route('invoices.show', $latest['invoice']),
                ];
            }
        }

        return $out;
    }

    /** @return list<array> */
    private function receivableConcentration(): array
    {
        $report = $this->ageing->report(InvoiceType::SELL);

        if ($report['total'] <= 0 || count($report['parties']) < self::CONCENTRATION_MIN_PARTIES) {
            return [];
        }

        $top = $report['parties'][0];
        $share = $top['total'] / $report['total'];

        if ($share < self::CONCENTRATION_SHARE) {
            return [];
        }

        return [[
            'type' => 'receivable_concentration',
            'severity' => 'info',
            'title' => __('Receivables are concentrated'),
            'detail' => __(':name holds :percent% of all open receivables.', [
                'name' => $top['name'],
                'percent' => localizeNumber((string) round($share * 100)),
            ]),
            'url' => route('ageing.receivables'),
        ]];
    }

    /** @return list<array> */
    private function bouncedCheques(): array
    {
        $bounced = $this->cheques->report(0)['receivable']['bounced'];

        if ($bounced->isEmpty()) {
            return [];
        }

        return [[
            'type' => 'bounced_cheques',
            'severity' => 'error',
            'title' => __('Bounced cheques need follow-up'),
            'detail' => __(':count bounced receivable cheque(s) are still outstanding.', ['count' => localizeNumber($bounced->count())]),
            'url' => route('cheques.index'),
        ]];
    }
}
