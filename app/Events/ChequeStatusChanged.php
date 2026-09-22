<?php

namespace App\Events;

use App\Enums\ChequeType;
use App\Models\Cheque;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * P6 (API v1 Platform). Fired by ChequeService::transition() after a
 * successful action. Consumed by WebhookEventSubscriber to feed the
 * webhook platform's `cheque.status_changed` event type.
 */
class ChequeStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Cheque $cheque,
        public readonly ChequeType $previousStatus,
        public readonly ChequeType $newStatus,
        public readonly string $action,
    ) {}
}
