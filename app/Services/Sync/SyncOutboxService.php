<?php

namespace App\Services\Sync;

use App\Enums\SyncOperation;
use App\Enums\SyncStatus;
use App\Models\SyncOutboxEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * D-071 decision 7: the generic Outbox mechanism (stage 2.4/2.5). Each entry
 * gets a stable, client-generated `operation_id` that never changes across
 * retries -- this is the idempotency key the server uses to recognize a
 * resent batch instead of double-applying it.
 */
class SyncOutboxService
{
    /**
     * @param  array<string, mixed>  $payloadOverrides  D-073 2.7B: lets an
     *         entity substitute a cross-device-safe reference (e.g.
     *         `customer_uuid`) for a local-only value its raw attributes
     *         carry (`customer_id`) -- a local auto-increment id means
     *         nothing on another device/server. Merged over the model's
     *         own attributes; empty for every entity that has no such
     *         reference (Customer/Product/Document today).
     */
    public function enqueue(Model $model, SyncOperation $operation, int $companyId, ?int $baseSyncVersion = null, array $payloadOverrides = []): SyncOutboxEntry
    {
        $payload = $operation === SyncOperation::DELETE
            ? ['uuid' => $model->uuid]
            : array_merge($model->attributesToArray(), $payloadOverrides);

        return SyncOutboxEntry::create([
            'operation_id' => (string) Str::uuid(),
            'entity_type' => $model->getMorphClass(),
            'entity_uuid' => $model->uuid,
            'operation' => $operation,
            'payload' => $payload,
            'base_sync_version' => $baseSyncVersion,
            'company_id' => $companyId,
            'status' => SyncStatus::PENDING,
        ]);
    }

    public function pending(int $companyId, int $limit = 100)
    {
        return SyncOutboxEntry::where('company_id', $companyId)
            ->where('status', SyncStatus::PENDING)
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function markSent(SyncOutboxEntry $entry): void
    {
        $entry->update(['sent_at' => now()]);
    }

    public function markAcknowledged(SyncOutboxEntry $entry): void
    {
        $entry->update(['status' => SyncStatus::SYNCED, 'acknowledged_at' => now()]);
    }

    public function markFailed(SyncOutboxEntry $entry, string $error): void
    {
        $entry->update([
            'status' => SyncStatus::REJECTED,
            'attempts' => $entry->attempts + 1,
            'last_error' => $error,
        ]);
    }

    public function markConflict(SyncOutboxEntry $entry, string $reason): void
    {
        $entry->update([
            'status' => SyncStatus::CONFLICT,
            'last_error' => $reason,
        ]);
    }
}
