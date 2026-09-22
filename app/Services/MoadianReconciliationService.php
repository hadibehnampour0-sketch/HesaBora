<?php

namespace App\Services;

use App\Enums\MoadianStatus;
use App\Models\Invoice;

/**
 * P12.1 (Moadian Live Status Inquiry). Re-checks every invoice whose most
 * recent Moadian submission attempt has a `referenceNumber` (something to
 * actually inquire about) but isn't yet a confirmed SUCCESS -- covers both
 * a genuinely still-processing submission and a previously-FAILED one
 * that may have since cleared. Never re-sends an invoice (that's
 * `MoadianService::sendInvoice()`'s job); this only asks the government
 * API for the current status of an already-submitted one.
 */
class MoadianReconciliationService
{
    public function __construct(
        private readonly MoadianService $moadianService,
        private readonly TaxReadinessService $readinessService,
    ) {}

    /**
     * @return int number of invoices whose Moadian status actually changed
     */
    public function reconcilePendingInvoices(int $companyId): int
    {
        $invoices = Invoice::with('latestMoadianHistory')
            ->whereHas('latestMoadianHistory', function ($query) {
                $query->whereNotNull('data->referenceNumber')
                    ->where(fn ($q) => $q->whereNull('data->status')->orWhere('data->status', '!=', 'SUCCESS'));
            })
            ->get();

        $changed = 0;

        foreach ($invoices as $invoice) {
            $history = $invoice->latestMoadianHistory;
            $referenceNumber = $history->data['referenceNumber'] ?? null;

            if (! $referenceNumber) {
                continue;
            }

            $previousStatus = MoadianStatus::fromData($history->data['status'] ?? null);
            $statusData = $this->moadianService->moadianStatus($referenceNumber, $invoice);
            $newStatus = MoadianStatus::fromData($statusData['status'] ?? null);

            if ($newStatus !== $previousStatus) {
                $changed++;
            }
        }

        if ($changed > 0) {
            $this->readinessService->calculateScore($companyId);
        }

        return $changed;
    }
}
