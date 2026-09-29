<?php

namespace App\Services;

use App\Enums\DailySettlementStatus;
use App\Models\DailySettlement;
use App\Models\DistributionVisitor;
use App\Models\RepCommission;
use Illuminate\Support\Collection;

/**
 * P12.4 (Field Operations Commission & Route Analytics Engine). Commission
 * is calculated strictly from `DailySettlement` rows with
 * `status = APPROVED` (P11's own settlement lifecycle) -- a submitted-but-
 * not-yet-approved settlement earns nothing yet, matching this app's
 * general "only a reviewed/approved record is a real financial event"
 * convention (e.g. InvoiceStatus::approvedOrSettled()).
 *
 * Tier thresholds/rates below are a REASONABLE DEFAULT, not a value the
 * owner specified -- a class constant for this pass, mirroring
 * TaxReadinessService::MATERIAL_EXPENSE_THRESHOLD's own precedent
 * (guardrail #49). A visitor with their OWN agreed commission rate
 * (`distribution_visitors.commission_rate`, set from the Visitors screen)
 * overrides the tier lookup entirely for that person -- the tiers remain
 * the company-wide default for everyone else.
 */
class RepCommissionEngineService
{
    /**
     * Ordered ascending by threshold; the LAST tier whose threshold the
     * rep's total settled amount meets or exceeds wins.
     *
     * @var array<int, array{threshold: float, rate: float, label: string}>
     */
    private const TIERS = [
        ['threshold' => 0, 'rate' => 1.0, 'label' => 'Bronze'],
        ['threshold' => 50_000_000, 'rate' => 2.0, 'label' => 'Silver'],
        ['threshold' => 200_000_000, 'rate' => 3.0, 'label' => 'Gold'],
    ];

    /**
     * @return Collection<int, RepCommission>
     */
    public function calculateForPeriod(int $companyId, string $periodStart, string $periodEnd): Collection
    {
        $totals = DailySettlement::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', DailySettlementStatus::APPROVED)
            ->whereBetween('settlement_date', [$periodStart, $periodEnd])
            ->selectRaw('user_id, SUM(total_invoiced_amount) as total')
            ->groupBy('user_id')
            ->get();

        $overrideRates = DistributionVisitor::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('user_id', $totals->pluck('user_id'))
            ->whereNotNull('commission_rate')
            ->pluck('commission_rate', 'user_id');

        return $totals->map(function ($row) use ($companyId, $periodStart, $periodEnd, $overrideRates) {
            $total = (float) $row->total;
            $overrideRate = $overrideRates->get($row->user_id);
            $tier = $overrideRate !== null
                ? ['rate' => (float) $overrideRate, 'label' => 'Custom']
                : $this->tierFor($total);

            // Guardrail #35: updateOrCreate()'s own lookup compares the raw
            // 'Y-m-d' argument against period_start/period_end's stored,
            // datetime-formatted ('date'-cast) value and never matches --
            // look the row up explicitly with whereDate() first instead.
            $existing = RepCommission::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('user_id', $row->user_id)
                ->whereDate('period_start', $periodStart)
                ->whereDate('period_end', $periodEnd)
                ->first();

            $attributes = [
                'total_settled_amount' => $total,
                'commission_rate' => $tier['rate'],
                'commission_amount' => round($total * $tier['rate'] / 100, 2),
                'tier_label' => $tier['label'],
                'calculated_at' => now(),
            ];

            if ($existing) {
                $existing->update($attributes);

                return $existing;
            }

            return RepCommission::create(array_merge($attributes, [
                'company_id' => $companyId,
                'user_id' => $row->user_id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ]));
        })->values();
    }

    /**
     * @return array{threshold: float, rate: float, label: string}
     */
    private function tierFor(float $totalSettledAmount): array
    {
        $selected = self::TIERS[0];

        foreach (self::TIERS as $tier) {
            if ($totalSettledAmount >= $tier['threshold']) {
                $selected = $tier;
            }
        }

        return $selected;
    }
}
