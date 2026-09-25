<?php

namespace App\Services\Integration;

/** Canonical events every platform adapter translates to (design doc B3). */
enum IntegrationEventType: string
{
    case ORDER_CREATED = 'order_created';
    case ORDER_CANCELLED = 'order_cancelled';
    case REFUND_ISSUED = 'refund_issued';
    case PAYOUT_SETTLED = 'payout_settled';
    case INVENTORY_CHANGED = 'inventory_changed';
    case CUSTOMER_UPSERTED = 'customer_upserted';
}
