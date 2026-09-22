<?php

namespace App\Models\Concerns;

use App\Enums\SyncOperation;
use App\Services\Sync\SyncContext;
use App\Services\Sync\SyncOutboxService;

/**
 * D-073 stage 2.7A -- Document Draft Sync. Unlike SyncsToOutbox
 * (Customer/Product, unconditional), a Document only enqueues while it is
 * company-scoped and a manual (no documentable link) Draft (approved_at
 * null): once approved, once it is an automatic document created by
 * another domain service (Invoice, Cheque, Settlement, ...), or when it
 * has no company_id at all (some legacy/import paths create a bare
 * Document with no company yet -- there is no device/company to sync it
 * to), it is out of scope until stage 2.7B/2.7D and must never enter the
 * sync pipeline.
 *
 * This relies on DocumentService::createDocument() associating a
 * `documentable` (if any) on the model BEFORE its first save, so the very
 * first `created` event already reflects the document's true final shape
 * (manual draft vs. automatic/linked) -- no deferral needed.
 *
 * No enqueue on delete at all -- D-073 decision 5 removes hard delete from
 * sync for financial documents entirely; there is no sync-side equivalent
 * to translate a local delete into, so a local delete simply never
 * reaches the outbox.
 */
trait SyncsDocumentDraftsToOutbox
{
    private ?int $syncBaseVersionBeforeUpdate = null;

    protected static function bootSyncsDocumentDraftsToOutbox(): void
    {
        static::created(function ($model) {
            if (SyncContext::isApplyingRemote() || ! $model->isDraftSyncEligible()) {
                return;
            }
            app(SyncOutboxService::class)->enqueue($model, SyncOperation::CREATE, $model->company_id, null);
        });

        static::updating(function ($model) {
            $model->syncBaseVersionBeforeUpdate = $model->getOriginal('sync_version');
        });

        static::updated(function ($model) {
            if (SyncContext::isApplyingRemote() || ! $model->isDraftSyncEligible()) {
                return;
            }
            app(SyncOutboxService::class)->enqueue($model, SyncOperation::UPDATE, $model->company_id, $model->syncBaseVersionBeforeUpdate);
        });
    }

    public function isDraftSyncEligible(): bool
    {
        return $this->company_id !== null && $this->approved_at === null && empty($this->documentable_type);
    }
}
