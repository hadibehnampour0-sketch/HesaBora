<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\TaxAlertEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * P12.2 (Tax Alert & Smart Notification Engine). Iterates every company,
 * same `Company::withoutGlobalScopes()->get()` pattern as
 * SyncMoadianStatusJob/GenerateRecurringInvoicesCommand. No active-company
 * config swap is needed here (unlike those two) because
 * TaxAlertEngineService queries every model by an explicit company_id
 * rather than relying on FiscalYearScope picking up the active company.
 */
class EvaluateTaxAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(TaxAlertEngineService $service): void
    {
        foreach (Company::withoutGlobalScopes()->get() as $company) {
            $service->evaluateCompanyRules($company->id);
        }
    }
}
