<?php

namespace App\Services\AiCopilot;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\WarehouseProductStock;
use App\Services\AgeingService;
use App\Services\ChequeRiskDashboardService;
use App\Services\PlainReportService;
use App\Services\TaxReadinessService;
use Illuminate\Support\Carbon;

/**
 * Gathers deterministic, company-scoped financial facts directly from Dakhl's
 * verified accounting and reporting services. The AI never guesses numbers;
 * it grounds all responses in these verified data snapshots.
 */
class FinancialKnowledgeService
{
    public function __construct(
        protected AgeingService $ageingService,
        protected ChequeRiskDashboardService $chequeService,
        protected TaxReadinessService $taxReadinessService,
        protected PlainReportService $plainReportService,
    ) {}

    public function getSalesSummary(int $companyId): array
    {
        $today = Carbon::today();
        $startOfWeek = $today->copy()->subDays(6)->startOfDay();
        $startOfMonth = $today->copy()->startOfMonth();

        $salesToday = (float) Invoice::where('invoice_type', InvoiceType::SELL)
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->whereDate('date', $today)
            ->sum('amount');

        $salesTodayCount = Invoice::where('invoice_type', InvoiceType::SELL)
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->whereDate('date', $today)
            ->count();

        $salesWeek = (float) Invoice::where('invoice_type', InvoiceType::SELL)
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->where('date', '>=', $startOfWeek)
            ->sum('amount');

        $salesMonth = (float) Invoice::where('invoice_type', InvoiceType::SELL)
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->where('date', '>=', $startOfMonth)
            ->sum('amount');

        $purchasesMonth = (float) Invoice::where('invoice_type', InvoiceType::BUY)
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->where('date', '>=', $startOfMonth)
            ->sum('amount');

        return [
            'sales_today' => $salesToday,
            'sales_today_count' => $salesTodayCount,
            'sales_7_days' => $salesWeek,
            'sales_this_month' => $salesMonth,
            'purchases_this_month' => $purchasesMonth,
            'net_operational_flow' => $salesMonth - $purchasesMonth,
        ];
    }

    public function getTopOverdueDebtors(int $limit = 5): array
    {
        $report = $this->ageingService->report(InvoiceType::SELL);
        $parties = array_slice($report['parties'] ?? [], 0, $limit);

        $result = [];
        foreach ($parties as $party) {
            $result[] = [
                'name' => $party['name'],
                'total_debt' => (float) $party['total'],
                'over_90_days' => (float) ($party['buckets'][2]['amount'] ?? 0),
                'invoices_count' => count($party['invoices'] ?? []),
            ];
        }

        return [
            'total_receivables' => (float) ($report['total'] ?? 0),
            'top_debtors' => $result,
        ];
    }

    public function getChequesDueSoon(int $days = 7): array
    {
        $report = $this->chequeService->report($days);
        $receivable = $report['receivable'] ?? [];
        $payable = $report['payable'] ?? [];

        $dueSoonReceivable = $receivable['due_soon'] ?? collect();
        $overdueReceivable = $receivable['overdue'] ?? collect();
        $bouncedReceivable = $receivable['bounced'] ?? collect();

        $dueSoonPayable = $payable['due_soon'] ?? collect();
        $overduePayable = $payable['overdue'] ?? collect();

        return [
            'receivable_due_soon_count' => $dueSoonReceivable->count(),
            'receivable_due_soon_amount' => (float) $dueSoonReceivable->sum('amount'),
            'receivable_overdue_count' => $overdueReceivable->count(),
            'receivable_overdue_amount' => (float) $overdueReceivable->sum('amount'),
            'receivable_bounced_count' => $bouncedReceivable->count(),
            'receivable_bounced_amount' => (float) $bouncedReceivable->sum('amount'),
            'payable_due_soon_count' => $dueSoonPayable->count(),
            'payable_due_soon_amount' => (float) $dueSoonPayable->sum('amount'),
            'payable_overdue_count' => $overduePayable->count(),
            'payable_overdue_amount' => (float) $overduePayable->sum('amount'),
        ];
    }

    public function getTaxReadiness(int $companyId): array
    {
        $log = $this->taxReadinessService->calculateScore($companyId);

        return [
            'score' => $log->score,
            'status' => $log->status,
            'recommendations' => $log->recommendations ?? [],
            'breakdown' => $log->metrics_breakdown ?? [],
        ];
    }

    public function getLowStockProducts(int $limit = 5): array
    {
        $products = Product::query()
            ->where('is_active', true)
            ->whereNotNull('min_order')
            ->get()
            ->filter(function (Product $product) {
                $stock = WarehouseProductStock::where('product_id', $product->id)->sum('quantity');

                return $stock <= ($product->min_order ?? 0);
            })
            ->take($limit)
            ->values();

        $items = [];
        foreach ($products as $p) {
            $items[] = [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'current_stock' => (float) WarehouseProductStock::where('product_id', $p->id)->sum('quantity'),
                'min_order' => (float) $p->min_order,
            ];
        }

        return $items;
    }

    public function getBankBalances(): array
    {
        $accounts = BankAccount::with('bank:id,name')->get();
        $list = [];
        $total = 0.0;

        foreach ($accounts as $acc) {
            $balance = (float) $acc->initial_balance;
            $total += $balance;
            $list[] = [
                'name' => $acc->name,
                'bank' => $acc->bank?->name ?? 'بانک',
                'account_number' => $acc->account_number,
                'balance' => $balance,
            ];
        }

        return [
            'total_balance' => $total,
            'accounts' => $list,
        ];
    }

    public function getFullSnapshot(int $companyId): array
    {
        return [
            'sales' => $this->getSalesSummary($companyId),
            'debtors' => $this->getTopOverdueDebtors(5),
            'cheques' => $this->getChequesDueSoon(7),
            'tax' => $this->getTaxReadiness($companyId),
            'low_stock' => $this->getLowStockProducts(5),
            'banks' => $this->getBankBalances(),
        ];
    }
}
