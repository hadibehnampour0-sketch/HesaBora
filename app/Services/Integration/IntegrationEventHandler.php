<?php

namespace App\Services\Integration;

/**
 * Turns a canonical event into domain effects. Implementations MUST use the existing services
 * (InvoiceService, PaymentService, ...) -- never write accounting tables directly.
 */
interface IntegrationEventHandler
{
    public function handle(CanonicalEvent $event): HandlerResult;
}
