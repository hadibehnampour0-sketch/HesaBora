<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\InstallmentSchedule;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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

        if ($user->can('invoices.index') || $user->can('documents.show')) {
            $data['blocks']['installments'] = $this->installmentsBlock($today);
        }

        if ($user->can('documents.show') || $user->can('bank-accounts.index')) {
            $cashBookSubjectId = config('amir.cash_book');
            $bankSubjectId = config('amir.bank');

            $cashBalance = $cashBookSubjectId ? abs(SubjectService::sumSubject($cashBookSubjectId, true, false, true)) : 0.0;
            $bankBalance = $bankSubjectId ? abs(SubjectService::sumSubject($bankSubjectId, true, false, true)) : 0.0;

            $data['blocks']['liquidity'] = [
                'cash' => $cashBalance,
                'bank' => $bankBalance,
                'total' => $cashBalance + $bankBalance,
            ];
        }

        $alerts = [];

        // Cheque alerts
        if ($user->can('cheques.index')) {
            $chequeData = $data['blocks']['cheques'] ?? $this->chequeBlock();
            if (($chequeData['receivable']['bounced']['count'] ?? 0) > 0) {
                $alerts[] = [
                    'type' => 'bounced_cheque',
                    'severity' => 'error',
                    'title' => 'چک‌های برگشتی نیازمند اقدام فوری',
                    'count' => $chequeData['receivable']['bounced']['count'],
                    'amount' => $chequeData['receivable']['bounced']['amount'],
                    'url' => route('cheques.index'),
                ];
            }
            if (($chequeData['receivable']['overdue']['count'] ?? 0) > 0) {
                $alerts[] = [
                    'type' => 'overdue_cheque',
                    'severity' => 'warning',
                    'title' => 'چک‌های دریافتی سررسید گذشته',
                    'count' => $chequeData['receivable']['overdue']['count'],
                    'amount' => $chequeData['receivable']['overdue']['amount'],
                    'url' => route('cheques.index'),
                ];
            }
            if (($chequeData['payable']['overdue']['count'] ?? 0) > 0) {
                $alerts[] = [
                    'type' => 'payable_overdue_cheque',
                    'severity' => 'warning',
                    'title' => 'چک‌های پرداختی سررسید گذشته',
                    'count' => $chequeData['payable']['overdue']['count'],
                    'amount' => $chequeData['payable']['overdue']['amount'],
                    'url' => route('cheques.index'),
                ];
            }
        }

        // Low stock alert
        if ($user->can('products.index')) {
            $lowStockProducts = Product::query()
                ->whereColumn('quantity', '<=', 'quantity_warning')
                ->where('quantity_warning', '>', 0)
                ->count();

            if ($lowStockProducts > 0) {
                $alerts[] = [
                    'type' => 'low_stock',
                    'severity' => 'warning',
                    'title' => 'کالاهای دارای کسری یا در مرز اتمام موجودی',
                    'count' => $lowStockProducts,
                    'url' => route('products.index'),
                ];
            }
        }

        // Pending invoices alert
        if ($user->can('invoices.index')) {
            $pendingInvoicesCount = Invoice::query()
                ->where('status', InvoiceStatus::PENDING)
                ->count();

            if ($pendingInvoicesCount > 0) {
                $alerts[] = [
                    'type' => 'pending_invoices',
                    'severity' => 'info',
                    'title' => 'فاکتورهای تایید نشده و در انتظار بررسی',
                    'count' => $pendingInvoicesCount,
                    'url' => route('invoices.index', ['invoice_type' => 'sell']),
                ];
            }
        }

        if ($alerts !== []) {
            $data['blocks']['alerts'] = $alerts;
        }

        $quickStart = $this->quickStartBlock($user);

        if ($quickStart !== []) {
            $data['blocks']['quick_start'] = $quickStart;
        }

        $insights = $this->insights->detect($user, $today);

        if ($insights !== []) {
            $data['blocks']['insights'] = $insights;
        }

        return $data;
    }

    /**
     * First-steps checklist for a brand-new company: shown only while a step the user is
     * allowed to perform is still undone. Read-only (three existence checks).
     *
     * @return list<array{key: string, done: bool, url: string}>
     */
    private function quickStartBlock(User $user): array
    {
        $steps = [];

        if ($user->can('customers.create')) {
            $steps[] = ['key' => 'customer', 'done' => Customer::query()->exists(), 'url' => route('customers.create')];
        }

        if ($user->can('products.create')) {
            $steps[] = ['key' => 'product', 'done' => Product::query()->exists(), 'url' => route('products.create')];
        }

        if ($user->can('invoices.create')) {
            $steps[] = ['key' => 'sale', 'done' => Invoice::query()->where('invoice_type', InvoiceType::SELL)->exists(), 'url' => route('invoices.create', ['invoice_type' => 'sell'])];
        }

        return collect($steps)->contains(fn ($step) => ! $step['done']) ? $steps : [];
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

    private function installmentsBlock(Carbon $today): array
    {
        $todayStr = $today->toDateString();
        $in7DaysStr = $today->copy()->addDays(7)->toDateString();

        if (! Schema::hasTable('installment_schedules')) {
            return [
                'today' => ['count' => 0, 'amount' => 0],
                'overdue' => ['count' => 0, 'amount' => 0],
                'due_soon' => ['count' => 0, 'amount' => 0],
                'items' => [],
            ];
        }

        try {
            $schedules = InstallmentSchedule::query()
                ->with(['contract.customer'])
                ->whereNull('paid_at')
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'PAID');
                })
                ->where('due_date', '<=', $in7DaysStr)
                ->orderBy('due_date')
                ->limit(10)
                ->get();

            $todayItems = $schedules->filter(fn ($s) => $s->due_date && Carbon::parse($s->due_date)->toDateString() === $todayStr);
            $overdueItems = $schedules->filter(fn ($s) => $s->due_date && Carbon::parse($s->due_date)->toDateString() < $todayStr);
            $dueSoonItems = $schedules->filter(fn ($s) => $s->due_date && Carbon::parse($s->due_date)->toDateString() > $todayStr && Carbon::parse($s->due_date)->toDateString() <= $in7DaysStr);

            return [
                'today' => [
                    'count' => $todayItems->count(),
                    'amount' => (float) $todayItems->sum(fn ($s) => $s->amount - ($s->paid_amount ?? 0)),
                ],
                'overdue' => [
                    'count' => $overdueItems->count(),
                    'amount' => (float) $overdueItems->sum(fn ($s) => $s->amount - ($s->paid_amount ?? 0)),
                ],
                'due_soon' => [
                    'count' => $dueSoonItems->count(),
                    'amount' => (float) $dueSoonItems->sum(fn ($s) => $s->amount - ($s->paid_amount ?? 0)),
                ],
                'items' => $schedules->map(function ($s) use ($today) {
                    $customerName = $s->contract?->customer?->name ?? 'مشتری اقساطی';
                    $phone = $s->contract?->customer?->phone ?? $s->contract?->customer?->mobile ?? '';
                    $dueDateCarbon = $s->due_date ? Carbon::parse($s->due_date) : null;
                    $diffDays = $dueDateCarbon ? $dueDateCarbon->diffInDays($today, false) : 0;

                    return [
                        'id' => $s->id,
                        'customer_name' => $customerName,
                        'customer_phone' => $phone,
                        'contract_number' => $s->contract?->contract_number ?? ('#'.$s->contract_id),
                        'installment_number' => $s->installment_number,
                        'amount' => (float) ($s->amount - ($s->paid_amount ?? 0)),
                        'due_date' => $dueDateCarbon?->toDateString(),
                        'is_overdue' => $diffDays > 0,
                        'is_today' => $diffDays === 0,
                        'diff_days' => abs($diffDays),
                    ];
                })->values()->all(),
            ];
        } catch (\Throwable) {
            return [
                'today' => ['count' => 0, 'amount' => 0],
                'overdue' => ['count' => 0, 'amount' => 0],
                'due_soon' => ['count' => 0, 'amount' => 0],
                'items' => [],
            ];
        }
    }
}
