<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\MoadianReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * P12.1 (Moadian Live Status Inquiry). Iterates every company (mirroring
 * `GenerateRecurringInvoicesCommand`'s established pattern exactly --
 * `MoadianHistory`/`Invoice` are company-scoped via `FiscalYearScope`, so
 * each company's own pending invoices are only visible while that company
 * is active) and reconciles pending Moadian submissions. Meant to be
 * scheduled periodically; safe to run concurrently/repeatedly since it
 * only ever reads-then-records a status, never re-sends anything.
 */
class SyncMoadianStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(MoadianReconciliationService $service): void
    {
        $companies = Company::withoutGlobalScopes()->get();

        foreach ($companies as $company) {
            $previous = config('active-company-id');
            config(['active-company-id' => $company->id]);

            try {
                $service->reconcilePendingInvoices($company->id);
            } finally {
                config(['active-company-id' => $previous]);
            }
        }
    }
}
