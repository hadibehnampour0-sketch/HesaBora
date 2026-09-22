<?php

namespace App\Models\Concerns;

use App\Enums\SyncOperation;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Subject;
use App\Services\Sync\SyncContext;
use App\Services\Sync\SyncOutboxService;

/**
 * D-079 stage 2.8B -- Offline Payment Creation. Payment is never made
 * two-phase (D-078 decision 1): a Payment only ever enqueues a CREATE, at
 * the exact moment the existing PaymentService already created and posted
 * it. There is no UPDATE/DELETE enqueue -- D-079 decision 7 rejects hard
 * delete via Sync outright, and there is no existing domain concept of
 * editing a posted Payment's core fields to enqueue in the first place.
 *
 * Cheque-linked payments are excluded (D-078 decision 6: Cheque lifecycle
 * sync is out of scope) -- only Credit Payments and Invoice-linked
 * Payments are eligible.
 */
trait SyncsPaymentsToOutbox
{
    protected static function bootSyncsPaymentsToOutbox(): void
    {
        static::created(function ($model) {
            if (SyncContext::isApplyingRemote() || ! $model->isPaymentSyncEligible()) {
                return;
            }
            app(SyncOutboxService::class)->enqueue($model, SyncOperation::CREATE, $model->resolvedCompanyIdForSync(), null, $model->paymentSyncPayloadOverrides());
        });
    }

    public function isPaymentSyncEligible(): bool
    {
        return $this->cheque_id === null && $this->resolvedCompanyIdForSync() !== null;
    }

    /**
     * Payment has no reliable top-level `company_id` of its own (nullable;
     * see the migration history) -- it is derived through its Document or
     * its payer Customer. Some legacy/import paths (e.g.
     * FiscalYearService) create a bare Payment before either is resolvable
     * -- such a row has no company/device to sync to, so it is correctly
     * excluded from Payment Sync entirely (same fix shape as
     * SyncsDocumentDraftsToOutbox's own company_id guard, Entry 15).
     */
    public function resolvedCompanyIdForSync(): ?int
    {
        return $this->document?->company_id ?? $this->payer?->company_id;
    }

    /**
     * D-079: local auto-increment ids (`payer_id`/`invoice_id`/
     * `settlement_subject_id`) mean nothing on another device/server --
     * the outbox payload carries their canonical uuids instead, resolved
     * on the receiving side the same way Invoice's `customer_uuid`
     * already is (D-073 decision 6).
     */
    public function paymentSyncPayloadOverrides(): array
    {
        $overrides = [
            'payment_type' => $this->invoice_id ? 'invoice' : 'credit',
            'subject_uuid' => Subject::withoutGlobalScopes()->find($this->settlement_subject_id)?->uuid,
        ];

        if ($this->invoice_id) {
            $overrides['invoice_uuid'] = Invoice::withoutGlobalScopes()->find($this->invoice_id)?->uuid;
        } else {
            $overrides['customer_uuid'] = Customer::withoutGlobalScopes()->find($this->payer_id)?->uuid;
        }

        return $overrides;
    }
}
