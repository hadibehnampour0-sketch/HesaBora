<?php

namespace App\Enums;

/**
 * D-071 decision 7: sync is event/change-based, never a full-database
 * comparison. Each Outbox/Inbox entry records which kind of change happened.
 */
enum SyncOperation: int
{
    case CREATE = 1;
    case UPDATE = 2;
    case DELETE = 3;

    /**
     * D-075 decision 6: Finalize is its own operation, not a flavor of
     * UPDATE -- it consumes a number from the device's numbering lease and
     * makes it immutable, which a plain field UPDATE never does. Routed
     * through the exact same idempotent operation_id pipeline as every
     * other operation (D-071 decision 7 / D-072 rule 2).
     */
    case FINALIZE = 4;

    /**
     * D-076 decision 1: FINALIZED and APPROVED/POSTED are two separate
     * operations, never one. Approve asks the server -- the sole authority
     * for posting -- to accept the real accounting effect.
     */
    case APPROVE = 5;

    public function label(): string
    {
        return __(match ($this) {
            self::CREATE => 'Create',
            self::UPDATE => 'Update',
            self::DELETE => 'Delete',
            self::FINALIZE => 'Finalize',
            self::APPROVE => 'Approve',
        });
    }
}
