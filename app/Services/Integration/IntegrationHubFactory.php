<?php

namespace App\Services\Integration;

use App\Services\Integration\Handlers\OrderImportHandler;

/** Builds an IntegrationHub with every real handler registered -- the one place that wiring lives. */
class IntegrationHubFactory
{
    public function __construct(private readonly OrderImportHandler $orderImportHandler) {}

    public function make(): IntegrationHub
    {
        $hub = new IntegrationHub;
        $hub->registerHandler(IntegrationEventType::ORDER_CREATED, $this->orderImportHandler);

        return $hub;
    }
}
