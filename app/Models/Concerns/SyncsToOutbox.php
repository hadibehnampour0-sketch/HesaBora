<?php

namespace App\Models\Concerns;

use App\Enums\SyncOperation;
use App\Services\Sync\SyncContext;
use App\Services\Sync\SyncOutboxService;

/**
 * D-071 stage 2.5: real enqueue-on-mutation wiring for the Customer/Product
 * sync pilot ONLY (per the owner's gate -- "no automatic sync from day one
 * for anything beyond Customer/Product"). Deliberately a separate trait from
 * HasSyncEnvelope so Document/Invoice/Payment/Transaction keep their
 * identity/version columns (stage 2.3) without silently starting to sync
 * (stages 2.7/2.8 are not started).
 */
trait SyncsToOutbox
{
    private ?int $syncBaseVersionBeforeUpdate = null;

    protected static function bootSyncsToOutbox(): void
    {
        static::created(function ($model) {
            if (SyncContext::isApplyingRemote()) {
                return;
            }
            app(SyncOutboxService::class)->enqueue($model, SyncOperation::CREATE, $model->company_id, null);
        });

        static::updating(function ($model) {
            $model->syncBaseVersionBeforeUpdate = $model->getOriginal('sync_version');
        });

        static::updated(function ($model) {
            if (SyncContext::isApplyingRemote()) {
                return;
            }
            app(SyncOutboxService::class)->enqueue($model, SyncOperation::UPDATE, $model->company_id, $model->syncBaseVersionBeforeUpdate);
        });

        static::deleted(function ($model) {
            if (SyncContext::isApplyingRemote()) {
                return;
            }
            app(SyncOutboxService::class)->enqueue($model, SyncOperation::DELETE, $model->company_id, $model->sync_version);
        });
    }
}
