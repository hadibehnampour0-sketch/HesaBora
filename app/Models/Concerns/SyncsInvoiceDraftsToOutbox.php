<?php

namespace App\Models\Concerns;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\SyncOperation;
use App\Models\Customer;
use App\Services\Sync\SyncContext;
use App\Services\Sync\SyncOutboxService;

/**
 * D-073 stage 2.7B -- Invoice Draft Sync. Mirrors
 * SyncsDocumentDraftsToOutbox's conditional-enqueue shape: an Invoice only
 * enqueues while it is company-scoped, has no `document_id` yet (in this
 * domain, a Document is only created together with approval -- see
 * InvoiceService::createInvoice() -- so a genuinely Draft invoice never has
 * one), is not in an approved/settled status, and is not a return/void
 * type (those reference another Invoice by `returned_invoice_id`, a
 * cross-device chain not yet supported -- out of scope until a later
 * stage).
 *
 * No enqueue on delete -- same reasoning as Document (D-073 decision 5):
 * hard delete never enters the sync pipeline for a financial record.
 */
trait SyncsInvoiceDraftsToOutbox
{
    private ?int $syncBaseVersionBeforeUpdate = null;

    protected static function bootSyncsInvoiceDraftsToOutbox(): void
    {
        static::created(function ($model) {
            if (SyncContext::isApplyingRemote() || ! $model->isDraftSyncEligible()) {
                return;
            }
            app(SyncOutboxService::class)->enqueue($model, SyncOperation::CREATE, $model->company_id, null, $model->invoiceSyncPayloadOverrides());
        });

        static::updating(function ($model) {
            $model->syncBaseVersionBeforeUpdate = $model->getOriginal('sync_version');
        });

        static::updated(function ($model) {
            if (SyncContext::isApplyingRemote() || ! $model->isDraftSyncEligible()) {
                return;
            }
            app(SyncOutboxService::class)->enqueue($model, SyncOperation::UPDATE, $model->company_id, $model->syncBaseVersionBeforeUpdate, $model->invoiceSyncPayloadOverrides());
        });
    }

    /**
     * D-073 decision 6: `customer_id` is a local auto-increment id and
     * means nothing on another device/server -- the outbox payload must
     * carry the Customer's canonical `uuid` instead, which is what
     * SyncPilotService::resolveInvoiceDependency() looks up on the
     * receiving side.
     */
    public function invoiceSyncPayloadOverrides(): array
    {
        $customer = Customer::withoutGlobalScopes()->find($this->customer_id);

        return ['customer_uuid' => $customer?->uuid];
    }

    /**
     * D-073 2.7B: an explicit allow-list, not a negation of
     * isApprovedOrSettled() -- InvoiceStatus::APPROVED_INACTIVE is an
     * approved (just deactivated) status that isApprovedOrSettled() does
     * NOT include, and it must still count as having a real accounting
     * history, not a Draft.
     */
    private const DRAFT_LIKE_STATUSES = [
        InvoiceStatus::PENDING,
        InvoiceStatus::PRE_INVOICE,
        InvoiceStatus::UNAPPROVED,
        InvoiceStatus::REJECTED,
        InvoiceStatus::READY_TO_APPROVE,
    ];

    public function isDraftSyncEligible(): bool
    {
        return $this->company_id !== null
            && $this->document_id === null
            && in_array($this->status, self::DRAFT_LIKE_STATUSES, true)
            && ! in_array($this->invoice_type, [InvoiceType::RETURN_BUY, InvoiceType::RETURN_SELL, InvoiceType::VOID], true);
    }
}
