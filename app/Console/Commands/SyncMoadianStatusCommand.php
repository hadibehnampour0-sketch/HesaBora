<?php

namespace App\Console\Commands;

use App\Jobs\SyncMoadianStatusJob;
use Illuminate\Console\Command;

/**
 * P12.1 (Moadian Live Status Inquiry). Manual/scheduled trigger for
 * SyncMoadianStatusJob -- mirrors `recurring:generate`'s own command
 * pattern. Not registered on a schedule by this pass (no cadence was
 * specified); an operator can `php artisan schedule` this or run it
 * manually/via cron.
 */
class SyncMoadianStatusCommand extends Command
{
    protected $signature = 'moadian:sync-status';

    protected $description = 'Re-check the Moadian submission status of every pending invoice, across all companies.';

    public function handle(): int
    {
        SyncMoadianStatusJob::dispatchSync();

        $this->info('Moadian status sync dispatched.');

        return self::SUCCESS;
    }
}
