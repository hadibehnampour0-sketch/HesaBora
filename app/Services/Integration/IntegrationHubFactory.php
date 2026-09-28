<?php

namespace App\Services\Integration;

use App\Services\Integration\Handlers\OrderCancelHandler;
use App\Services\Integration\Handlers\OrderImportHandler;

/**
 * Builds an IntegrationHub with every real handler registered -- the one place that wiring
 * lives. `REFUND_ISSUED` deliberately has no handler registered (see OrderCancelHandler's own
 * docblock) -- it stays UNHANDLED and visible in the review queue rather than being guessed at.
 */
class IntegrationHubFactory
{
    public function __construct(
        private readonly OrderImportHandler $orderImportHandler,
        private readonly OrderCancelHandler $orderCancelHandler,
    ) {}

    public function make(): IntegrationHub
    {
        $hub = new IntegrationHub;
        $hub->registerHandler(IntegrationEventType::ORDER_CREATED, $this->orderImportHandler);
        $hub->registerHandler(IntegrationEventType::ORDER_CANCELLED, $this->orderCancelHandler);

        return $hub;
    }
}
