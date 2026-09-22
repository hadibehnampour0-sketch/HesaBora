<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\VisitPlanStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\RouteMetric;
use App\Models\SalesRoute;
use App\Models\VisitPlan;
use Illuminate\Support\Collection;

/**
 * P12.4 (Field Operations Commission & Route Analytics Engine). Three
 * metrics per route per period, each a reasonable approximation from
 * existing P11 data rather than a new tracking mechanism:
 *
 * - Visit completion rate: COMPLETED VisitPlans / total VisitPlans for
 *   that route in the period (0 when the route has no plans at all).
 * - Average invoice value: AVG(amount) of approved/settled SELL invoices
 *   billed to the route's assigned customers in the period.
 * - Cash collection ratio: SUM(Payment.amount) recorded against those
 *   same invoices, divided by SUM(invoice.amount) -- how much of what was
 *   invoiced to this route's customers has actually been collected.
 *   Deliberately uses `Payment` (direct invoice payments) only, not
 *   `PaymentAllocation` (advance/credit payments applied later) -- adding
 *   that second collection path is a reasonable future refinement, not
 *   done here to keep this pass's scope bounded.
 */
class RouteAnalyticsService
{
    /**
     * @return Collection<int, RouteMetric>
     */
    public function analyzeForPeriod(int $companyId, string $periodStart, string $periodEnd): Collection
    {
        $routes = SalesRoute::withoutGlobalScopes()->where('company_id', $companyId)->get();

        return $routes->map(function (SalesRoute $route) use ($companyId, $periodStart, $periodEnd) {
            $visitCompletionRate = $this->visitCompletionRate($route, $periodStart, $periodEnd);
            [$averageInvoiceValue, $cashCollectionRatio] = $this->invoiceMetrics($route, $periodStart, $periodEnd);

            // Guardrail #35: see RepCommissionEngineService's identical fix
            // -- updateOrCreate()'s raw-string lookup never matches a
            // 'date'-cast column's stored value.
            $existing = RouteMetric::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('route_id', $route->id)
                ->whereDate('period_start', $periodStart)
                ->whereDate('period_end', $periodEnd)
                ->first();

            $attributes = [
                'visit_completion_rate' => $visitCompletionRate,
                'average_invoice_value' => $averageInvoiceValue,
                'cash_collection_ratio' => $cashCollectionRatio,
                'calculated_at' => now(),
            ];

            if ($existing) {
                $existing->update($attributes);

                return $existing;
            }

            return RouteMetric::create(array_merge($attributes, [
                'company_id' => $companyId,
                'route_id' => $route->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ]));
        })->values();
    }

    private function visitCompletionRate(SalesRoute $route, string $periodStart, string $periodEnd): float
    {
        $plans = VisitPlan::withoutGlobalScopes()
            ->where('route_id', $route->id)
            ->whereBetween('plan_date', [$periodStart, $periodEnd])
            ->get();

        if ($plans->isEmpty()) {
            return 0.0;
        }

        $completed = $plans->where('status', VisitPlanStatus::COMPLETED)->count();

        return round(($completed / $plans->count()) * 100, 2);
    }

    /**
     * @return array{0: float, 1: float} [averageInvoiceValue, cashCollectionRatio]
     */
    private function invoiceMetrics(SalesRoute $route, string $periodStart, string $periodEnd): array
    {
        $customerIds = $route->customers()->pluck('customers.id');

        if ($customerIds->isEmpty()) {
            return [0.0, 0.0];
        }

        $invoices = Invoice::withoutGlobalScopes()
            ->where('company_id', $route->company_id)
            ->where('invoice_type', InvoiceType::SELL)
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->whereIn('customer_id', $customerIds)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->get();

        if ($invoices->isEmpty()) {
            return [0.0, 0.0];
        }

        $totalInvoiced = (float) $invoices->sum('amount');
        $averageInvoiceValue = round($totalInvoiced / $invoices->count(), 2);

        $totalCollected = (float) Payment::withoutGlobalScopes()
            ->whereIn('invoice_id', $invoices->pluck('id'))
            ->sum('amount');

        $cashCollectionRatio = $totalInvoiced > 0 ? round(min($totalCollected / $totalInvoiced, 1) * 100, 2) : 0.0;

        return [$averageInvoiceValue, $cashCollectionRatio];
    }
}
