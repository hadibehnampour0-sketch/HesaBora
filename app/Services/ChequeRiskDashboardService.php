<?php

namespace App\Services;

use App\Enums\ChequeType;
use App\Models\Cheque;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * P5.2 (Cheque Risk Dashboard). A pure read report over the existing
 * Cheque lifecycle (ChequeService, D-070) -- introduces no new table, no
 * new financial-posting path.
 */
class ChequeRiskDashboardService
{
    /**
     * Cheques still "in flight" (not yet cleared/bounced/returned/
     * cancelled), grouped into due-soon / overdue by direction.
     *
     * @return array{
     *   receivable: array{due_soon: Collection, overdue: Collection, total_outstanding: float},
     *   payable: array{due_soon: Collection, overdue: Collection, total_outstanding: float}
     * }
     */
    public function report(int $dueSoonDays = 7): array
    {
        $today = Carbon::today();
        $horizon = $today->copy()->addDays($dueSoonDays);

        $outstandingStatuses = [
            ChequeType::REGISTERED->value,
            ChequeType::DEPOSITED->value,
            ChequeType::ISSUED->value,
            ChequeType::BOUNCED->value,
        ];

        $receivable = Cheque::where('direction', ChequeType::RECEIVABLE->value)
            ->whereIn('status', $outstandingStatuses)
            ->get();
        $payable = Cheque::where('direction', ChequeType::PAYABLE->value)
            ->whereIn('status', $outstandingStatuses)
            ->get();

        return [
            'receivable' => $this->bucket($receivable, $today, $horizon),
            'payable' => $this->bucket($payable, $today, $horizon),
        ];
    }

    /**
     * @return array{due_soon: Collection, overdue: Collection, total_outstanding: float}
     */
    private function bucket(Collection $cheques, Carbon $today, Carbon $horizon): array
    {
        return [
            'due_soon' => $cheques->filter(fn (Cheque $c) => $c->due_date->gte($today) && $c->due_date->lte($horizon))->values(),
            'overdue' => $cheques->filter(fn (Cheque $c) => $c->due_date->lt($today) && $c->status !== ChequeType::BOUNCED)->values(),
            'bounced' => $cheques->filter(fn (Cheque $c) => $c->status === ChequeType::BOUNCED)->values(),
            'total_outstanding' => round((float) $cheques->sum('amount'), 2),
        ];
    }
}
