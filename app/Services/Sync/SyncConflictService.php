<?php

namespace App\Services\Sync;

use App\Enums\SyncConflictResolution;
use App\Enums\SyncConflictStatus;
use App\Enums\SyncOperation;
use App\Models\DeviceInstallation;
use App\Models\SyncConflict;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * D-072: the Conflict Resolution Workflow. Detection already happens in
 * SyncPilotService (stage 2.5) -- this service is only the back half:
 * review + resolve. Resolving NEVER mutates the target entity directly; it
 * always produces a new operation_id and replays it through the exact same
 * SyncPilotService::pushEntry() pipeline stage 2.5 already proved, which is
 * what makes resolution independently auditable and idempotent/retry-safe
 * by construction rather than by a second, parallel implementation.
 */
class SyncConflictService
{
    public function __construct(private readonly SyncPilotService $pilotService) {}

    public function openConflicts(int $companyId)
    {
        return SyncConflict::where('company_id', $companyId)
            ->where('status', SyncConflictStatus::OPEN)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{status: string, conflict_id: int, operation_id: ?string}
     */
    public function resolve(User $user, SyncConflict $conflict, SyncConflictResolution $resolution, ?array $manualPayload = null): array
    {
        if ($resolution === SyncConflictResolution::MANUAL_EDIT && empty($manualPayload)) {
            throw ValidationException::withMessages([
                'payload' => [__('A manual edit requires a payload.')],
            ]);
        }

        return DB::transaction(function () use ($user, $conflict, $resolution, $manualPayload) {
            $locked = SyncConflict::query()->lockForUpdate()->findOrFail($conflict->id);

            if (! $locked->isOpen()) {
                // Idempotent: a retry of an already-resolved conflict must
                // never attempt a second mutation.
                return $this->resultFor($locked);
            }

            if ($resolution === SyncConflictResolution::KEEP_SERVER) {
                return $this->resolveKeepServer($locked, $user);
            }

            return $this->resolveWithNewOperation($locked, $user, $resolution, $manualPayload);
        });
    }

    private function resolveKeepServer(SyncConflict $conflict, User $user): array
    {
        // The server's data already stands -- nothing to push, no new
        // operation. Just settle the conflict and its originating inbox
        // record as an explicit, resolved outcome.
        $conflict->update([
            'status' => SyncConflictStatus::RESOLVED,
            'resolution' => SyncConflictResolution::KEEP_SERVER,
            'resolved_by' => $user->id,
            'resolved_at' => now(),
        ]);

        $inboxEntry = $conflict->inboxEntry;
        if ($inboxEntry) {
            app(SyncInboxService::class)->markApplied($inboxEntry);
        }

        return $this->resultFor($conflict->fresh());
    }

    private function resolveWithNewOperation(SyncConflict $conflict, User $user, SyncConflictResolution $resolution, ?array $manualPayload): array
    {
        // The operation_id is generated and persisted BEFORE the push
        // attempt -- a retried resolve() call (network failure, duplicate
        // click) finds it already set and reuses it, so the underlying push
        // is idempotent by the same mechanism stage 2.5 already proved.
        if (empty($conflict->resulting_operation_id)) {
            $conflict->resulting_operation_id = (string) Str::uuid();
            $conflict->save();
        }

        $device = DeviceInstallation::findOrFail($conflict->origin_device_id);
        $modelClass = $this->pilotService->modelClassFor($conflict->entity_type);
        $currentServerVersion = $modelClass::where('uuid', $conflict->entity_uuid)->value('sync_version');

        $payload = $resolution === SyncConflictResolution::KEEP_LOCAL
            ? $conflict->local_snapshot
            : $manualPayload;

        $pushResult = $this->pilotService->pushEntry($device, [
            'operation_id' => $conflict->resulting_operation_id,
            'entity_type' => $conflict->entity_type,
            'entity_uuid' => $conflict->entity_uuid,
            'operation' => SyncOperation::UPDATE->value,
            'payload' => $payload,
            'base_sync_version' => $currentServerVersion,
        ]);

        if ($pushResult['status'] === 'accepted') {
            $conflict->update([
                'status' => SyncConflictStatus::RESOLVED,
                'resolution' => $resolution,
                'resolved_by' => $user->id,
                'resolved_at' => now(),
            ]);
        }
        // If the resolution's own push somehow produces a new conflict
        // (only possible under genuine concurrent access after we just
        // fetched the current version), this conflict stays OPEN --
        // intentionally not auto-retried, the reviewer must look again.

        return [
            'status' => $pushResult['status'],
            'conflict_id' => $conflict->id,
            'operation_id' => $conflict->resulting_operation_id,
        ];
    }

    private function resultFor(SyncConflict $conflict): array
    {
        return [
            'status' => $conflict->status === SyncConflictStatus::RESOLVED ? 'resolved' : 'open',
            'conflict_id' => $conflict->id,
            'operation_id' => $conflict->resulting_operation_id,
        ];
    }
}
