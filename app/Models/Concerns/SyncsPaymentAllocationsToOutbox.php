<?php

namespace App\Models\Concerns;

use App\Enums\SyncOperation;
use App\Models\Invoice;
use App\Services\Sync\SyncContext;
use App\Services\Sync\SyncOutboxService;

/**
 * D-083.2 -- PaymentAllocation local Outbox enqueue, symmetric with
 * SyncsPaymentsToOutbox: an Allocation only ever enqueues a CREATE, at the
 * exact moment the existing PaymentService::allocate()/allocatePayment()
 * already created it. There is no UPDATE/DELETE enqueue (D-081 decision 7).
 *
 * Only Invoice-targeted allocations are eligible -- SettlementInstallment
 * allocations (D-070) stay out of Payment Allocation Sync's scope, matching
 * the server-side boundary already enforced by
 * SyncPilotService::paymentAllocationSyncViolation().
 */
trait SyncsPaymentAllocationsToOutbox
{
    protected static function bootSyncsPaymentAllocationsToOutbox(): void
    {
        static::created(function ($model) {
            if (SyncContext::isApplyingRemote() || ! $model->isPaymentAllocationSyncEligible()) {
                return;
            }
            app(SyncOutboxService::class)->enqueue($model, SyncOperation::CREATE, $model->resolvedCompanyIdForSync(), null, $model->paymentAllocationSyncPayloadOverrides());
        });
    }

    public function isPaymentAllocationSyncEligible(): bool
    {
        return $this->allocatable_type === Invoice::class && $this->resolvedCompanyIdForSync() !== null;
    }

    /**
     * PaymentAllocation has no company_id of its own -- derived through its
     * Payment, same shape as SyncsPaymentsToOutbox::resolvedCompanyIdForSync().
     * An allocation whose Payment has no resolvable company (bare-row
     * pattern, tracked as a separate Open Engineering Item) is correctly
     * excluded from sync rather than crashing.
     */
    public function resolvedCompanyIdForSync(): ?int
    {
        return $this->payment?->document?->company_id ?? $this->payment?->payer?->company_id;
    }

    /**
     * D-081: local auto-increment ids mean nothing on another device/server
     * -- the outbox payload carries canonical uuids, resolved on the
     * receiving side the same way SyncPilotService::applyPaymentAllocationCreate()
     * already expects (`payment_uuid`, `allocatable_type` = 'invoice',
     * `allocatable_uuid`).
     */
    public function paymentAllocationSyncPayloadOverrides(): array
    {
        return [
            'payment_uuid' => $this->payment?->uuid,
            'allocatable_type' => 'invoice',
            'allocatable_uuid' => Invoice::withoutGlobalScopes()->find($this->allocatable_id)?->uuid,
        ];
    }
}
