<?php

namespace App\Models\Concerns;

use App\Enums\SyncStatus;
use App\Models\DeviceInstallation;
use Illuminate\Support\Str;

/**
 * D-071 decision 2 + decision 7: the shared Sync Contract envelope for any
 * entity with distributed/cross-device identity. `uuid` is the canonical
 * identity shared across runtimes; the model's own auto-increment `id`
 * stays a purely local surrogate key and is never used for cross-device
 * reference. `sync_version` bumps on every local mutation so the server can
 * detect stale writes; `sync_status` distinguishes business/financial state
 * from sync state (D-071 decision 5 -- CONFLICT/REJECTED are first-class,
 * visible states, never a silent rollback).
 *
 * Attaching this trait does not, by itself, enqueue anything for sync --
 * that is the Outbox/Inbox layer (2.4) and the per-domain pilot wiring
 * (2.5+). This trait only guarantees every distributed entity carries a
 * stable identity and a place to record sync state.
 */
trait HasSyncEnvelope
{
    protected static function bootHasSyncEnvelope(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }

            // DB column defaults are not reflected back onto a freshly
            // created in-memory model without a refresh(); set them
            // explicitly so sync_version/sync_status are correct immediately.
            if (is_null($model->sync_version)) {
                $model->sync_version = 1;
            }
            if (is_null($model->sync_status)) {
                $model->sync_status = SyncStatus::SYNCED;
            }
        });

        // Auto-bump sync_version on any real business-field change, unless
        // the caller already set sync_version explicitly (that's what
        // applying an incoming sync operation does -- it sets the resolved
        // version itself rather than letting this guess).
        static::updating(function ($model) {
            $envelopeColumns = ['sync_version', 'sync_status', 'origin_device_id', 'updated_at'];
            $hasBusinessChange = collect($model->getDirty())->keys()->diff($envelopeColumns)->isNotEmpty();

            if ($hasBusinessChange && ! $model->isDirty('sync_version')) {
                $model->sync_version = ((int) $model->getOriginal('sync_version', 0)) + 1;
            }
        });
    }

    public function initializeHasSyncEnvelope(): void
    {
        $this->mergeCasts([
            'sync_status' => SyncStatus::class,
        ]);
    }

    public function originDevice()
    {
        return $this->belongsTo(DeviceInstallation::class, 'origin_device_id');
    }

    public function markSynced(): void
    {
        $this->update(['sync_status' => SyncStatus::SYNCED]);
    }

    public function markConflict(): void
    {
        $this->update(['sync_status' => SyncStatus::CONFLICT]);
    }

    public function markRejected(): void
    {
        $this->update(['sync_status' => SyncStatus::REJECTED]);
    }

    public function needsSyncReview(): bool
    {
        return $this->sync_status?->needsReview() ?? false;
    }
}
