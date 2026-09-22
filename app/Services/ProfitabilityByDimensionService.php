<?php

namespace App\Services;

use App\Models\AnalyticDimension;
use App\Models\Subject;
use Illuminate\Support\Collection;

/**
 * P5.4 (Profitability by Analytic Dimension). Reuses AnalyticDimension's
 * own transaction-tagging (P3.1) and this app's existing config-based
 * subject classification (`config('amir.income')`/`sales_revenue`/
 * `service_revenue` for revenue roots, `cost`/`sundry_cost`/
 * `cost_of_goods_sold`/`cogs_service` for expense/COGS roots -- the exact
 * same convention PaymentService::settlementSubjectIds() already relies
 * on for bank/cash) rather than inventing a new revenue/expense
 * classification scheme.
 */
class ProfitabilityByDimensionService
{
    private function revenueSubjectIds(): array
    {
        return $this->descendantsOf(['income', 'sales_revenue', 'service_revenue']);
    }

    private function expenseSubjectIds(): array
    {
        return $this->descendantsOf(['cost', 'sundry_cost', 'cost_of_goods_sold', 'cogs_service']);
    }

    private function descendantsOf(array $configKeys): array
    {
        $ids = [];
        foreach ($configKeys as $key) {
            $rootId = (int) config('amir.'.$key);
            if (! $rootId) {
                continue;
            }
            $root = Subject::find($rootId);
            if ($root) {
                $ids = array_merge($ids, $root->getAllDescendantIds());
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array{revenue: float, expense: float, profit: float}
     */
    public function forDimension(AnalyticDimension $dimension): array
    {
        $revenueIds = $this->revenueSubjectIds();
        $expenseIds = $this->expenseSubjectIds();

        $transactions = $dimension->transactions()->get(['transactions.id', 'transactions.subject_id', 'transactions.value']);

        $revenue = 0.0;
        $expense = 0.0;

        foreach ($transactions as $transaction) {
            if (in_array($transaction->subject_id, $revenueIds, true)) {
                $revenue += (float) $transaction->value;
            } elseif (in_array($transaction->subject_id, $expenseIds, true)) {
                $expense += abs((float) $transaction->value);
            }
        }

        $revenue = round($revenue, 2);
        $expense = round($expense, 2);

        return [
            'revenue' => $revenue,
            'expense' => $expense,
            'profit' => round($revenue - $expense, 2),
        ];
    }

    /**
     * @return Collection<int, array{dimension_id: int, dimension_name: string, revenue: float, expense: float, profit: float}>
     */
    public function report(\App\Enums\AnalyticDimensionType $type): Collection
    {
        return AnalyticDimension::where('type', $type)
            ->get()
            ->map(function (AnalyticDimension $dimension) {
                $figures = $this->forDimension($dimension);

                return [
                    'dimension_id' => $dimension->id,
                    'dimension_name' => $dimension->name,
                    ...$figures,
                ];
            });
    }
}
