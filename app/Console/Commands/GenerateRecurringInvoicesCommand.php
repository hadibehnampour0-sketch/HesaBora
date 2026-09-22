<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use App\Services\RecurringOperationService;
use Illuminate\Console\Command;

/**
 * P3.3 (Recurring Operations). Iterates every Company (RecurringInvoiceTemplate
 * carries FiscalYearScope, so each company's templates are only visible
 * while that company is the active one) and generates every due template's
 * invoice. Meant to be scheduled daily; RecurringOperationService's own
 * locked, next_run_date-gated transaction is what actually prevents
 * duplicate generation, not this command -- running it twice in the same
 * day is always safe.
 */
class GenerateRecurringInvoicesCommand extends Command
{
    protected $signature = 'recurring:generate {--company= : Only generate for this company id}';

    protected $description = 'Generate invoices for every due recurring template, across all companies.';

    public function handle(RecurringOperationService $service): int
    {
        $companies = $this->option('company')
            ? Company::withoutGlobalScopes()->where('id', $this->option('company'))->get()
            : Company::withoutGlobalScopes()->get();

        $totalGenerated = 0;

        foreach ($companies as $company) {
            $previous = config('active-company-id');
            config(['active-company-id' => $company->id]);

            $user = $company->users()->first() ?? User::query()->first();
            if (! $user) {
                config(['active-company-id' => $previous]);

                continue;
            }

            $generated = $service->generateDue($user);
            $totalGenerated += $generated->count();

            if ($generated->isNotEmpty()) {
                $this->info("Company {$company->id} ({$company->name}): generated {$generated->count()} invoice(s).");
            }

            config(['active-company-id' => $previous]);
        }

        $this->info("Done. Total invoices generated: {$totalGenerated}.");

        return self::SUCCESS;
    }
}
