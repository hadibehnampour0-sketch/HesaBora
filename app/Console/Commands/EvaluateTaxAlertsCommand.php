<?php

namespace App\Console\Commands;

use App\Jobs\EvaluateTaxAlertsJob;
use Illuminate\Console\Command;

/**
 * P12.2 (Tax Alert & Smart Notification Engine). Manual/scheduled trigger
 * for EvaluateTaxAlertsJob, mirroring `moadian:sync-status`'s own pattern.
 * Not registered on a schedule by this pass (no cadence was specified).
 */
class EvaluateTaxAlertsCommand extends Command
{
    protected $signature = 'tax-alerts:evaluate';

    protected $description = 'Evaluate tax alert rules for every company and raise new alerts where conditions are met.';

    public function handle(): int
    {
        EvaluateTaxAlertsJob::dispatchSync();

        $this->info('Tax alert evaluation dispatched.');

        return self::SUCCESS;
    }
}
