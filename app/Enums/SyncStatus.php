<?php

namespace App\Enums;

/**
 * D-071 decision 5: a server-rejected operation is a first-class visible
 * state, never a silent rollback. CONFLICT and REJECTED are distinct from
 * PENDING/SYNCED so the UI can surface them explicitly.
 */
enum SyncStatus: int
{
    case PENDING = 1;
    case SYNCED = 2;
    case CONFLICT = 3;
    case REJECTED = 4;

    /**
     * D-073 decision 6 (dependency-aware sync): a parent the entity depends
     * on (e.g. an Invoice's Customer) has not been accepted on this side
     * yet. Deliberately distinct from CONFLICT/REJECTED -- it is not a
     * decision a human needs to make, just a retry once the dependency is
     * satisfied. Resending the same operation_id re-attempts resolution
     * instead of replaying a cached terminal outcome.
     */
    case PENDING_DEPENDENCY = 5;

    public function label(): string
    {
        return __(match ($this) {
            self::PENDING => 'Pending sync',
            self::SYNCED => 'Synced',
            self::CONFLICT => 'Sync conflict',
            self::REJECTED => 'Rejected by server',
            self::PENDING_DEPENDENCY => 'Waiting for a dependency',
        });
    }

    public function needsReview(): bool
    {
        return in_array($this, [self::CONFLICT, self::REJECTED], true);
    }
}
