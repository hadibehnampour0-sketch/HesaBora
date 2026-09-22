<?php

namespace App\Services\Sync;

use App\Enums\SyncOperation;
use App\Enums\SyncStatus;
use App\Models\DeviceInstallation;
use App\Models\SyncInboxEntry;
use Illuminate\Support\Str;

/**
 * D-071 decision 7 + decision 5: the generic Inbox mechanism (stage 2.4).
 * receive() only stages an incoming operation -- it does NOT validate or
 * apply it to the real domain tables. That dispatch (upserting a Customer,
 * rejecting a stale Invoice write, etc.) is entity-specific and belongs to
 * the pilot wiring (2.5) and later stages, which call reject()/markApplied()/
 * markConflict() below to record the outcome. This separation keeps a
 * server-rejected operation a first-class, auditable row (decision 5)
 * instead of a silent in-place mutation.
 */
class SyncInboxService
{
    public function receive(string $entityType, string $entityUuid, SyncOperation $operation, array $payload, DeviceInstallation $device, ?string $operationId = null, ?int $baseSyncVersion = null): SyncInboxEntry
    {
        return SyncInboxEntry::create([
            'operation_id' => $operationId ?? (string) Str::uuid(),
            'entity_type' => $entityType,
            'entity_uuid' => $entityUuid,
            'operation' => $operation,
            'payload' => $payload,
            'base_sync_version' => $baseSyncVersion,
            'origin_device_id' => $device->id,
            'company_id' => $device->company_id,
            'status' => SyncStatus::PENDING,
        ]);
    }

    public function markApplied(SyncInboxEntry $entry): void
    {
        $entry->update(['status' => SyncStatus::SYNCED, 'applied_at' => now()]);
    }

    public function reject(SyncInboxEntry $entry, string $reason): void
    {
        $entry->update(['status' => SyncStatus::REJECTED, 'rejection_reason' => $reason]);
    }

    public function markConflict(SyncInboxEntry $entry, string $reason, ?string $serverVersion = null, ?string $localVersion = null): void
    {
        $entry->update([
            'status' => SyncStatus::CONFLICT,
            'rejection_reason' => $reason,
            'server_version' => $serverVersion,
            'local_version' => $localVersion,
        ]);
    }

    /**
     * D-073 decision 6: an unresolved parent dependency (e.g. an Invoice's
     * Customer not yet accepted) is never a dropped record or a failed
     * batch -- it is this explicit, retryable state instead.
     */
    public function markPendingDependency(SyncInboxEntry $entry, string $reason): void
    {
        $entry->update(['status' => SyncStatus::PENDING_DEPENDENCY, 'rejection_reason' => $reason]);
    }

    public function needsReview(int $companyId)
    {
        return SyncInboxEntry::where('company_id', $companyId)
            ->whereIn('status', [SyncStatus::CONFLICT, SyncStatus::REJECTED])
            ->orderByDesc('id')
            ->get();
    }
}
