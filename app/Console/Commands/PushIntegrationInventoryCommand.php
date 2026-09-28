<?php

namespace App\Console\Commands;

use App\Models\IntegrationConnection;
use App\Services\Integration\IntegrationInventoryPushService;
use Illuminate\Console\Command;

/** Periodic reconciliation pass: push every active connection's current stock levels out. */
class PushIntegrationInventoryCommand extends Command
{
    protected $signature = 'integrations:push-inventory';

    protected $description = 'Push current Dakhl stock levels to every active external-platform connection';

    public function handle(IntegrationInventoryPushService $service): int
    {
        foreach (IntegrationConnection::withoutGlobalScopes()->where('is_active', true)->get() as $connection) {
            $previousActiveCompany = config('active-company-id');
            config(['active-company-id' => $connection->company_id]);

            try {
                $result = $service->pushAll($connection);
                $this->info("Connection #{$connection->id} ({$connection->name}): {$result['pushed']} pushed, {$result['failed']} failed.");
            } finally {
                config(['active-company-id' => $previousActiveCompany]);
            }
        }

        return self::SUCCESS;
    }
}
