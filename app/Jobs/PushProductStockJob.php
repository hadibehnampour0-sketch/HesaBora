<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Integration\IntegrationInventoryPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched by WarehouseProductStockObserver whenever a product's on-hand quantity changes.
 * A platform being slow/down never blocks or fails the stock mutation that triggered this --
 * same "delivery is always via a queued job, never inline" principle WebhookService already
 * established for outbound webhooks.
 */
class PushProductStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $productId) {}

    public function handle(IntegrationInventoryPushService $service): void
    {
        $product = Product::withoutGlobalScopes()->find($this->productId);
        if (! $product) {
            return;
        }

        $previousActiveCompany = config('active-company-id');
        config(['active-company-id' => $product->company_id]);

        try {
            $service->pushProduct($this->productId);
        } finally {
            config(['active-company-id' => $previousActiveCompany]);
        }
    }
}
