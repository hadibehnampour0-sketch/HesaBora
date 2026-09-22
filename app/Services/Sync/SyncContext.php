<?php

namespace App\Services\Sync;

/**
 * Suppresses outbox enqueueing while the sync system itself is APPLYING an
 * incoming operation to a local table -- otherwise applying a remote
 * Customer update would re-enqueue it as if it were a new local change,
 * causing an infinite echo between devices.
 */
class SyncContext
{
    private static bool $applyingRemote = false;

    public static function applyingRemote(callable $callback): mixed
    {
        $previous = self::$applyingRemote;
        self::$applyingRemote = true;

        try {
            return $callback();
        } finally {
            self::$applyingRemote = $previous;
        }
    }

    public static function isApplyingRemote(): bool
    {
        return self::$applyingRemote;
    }
}
