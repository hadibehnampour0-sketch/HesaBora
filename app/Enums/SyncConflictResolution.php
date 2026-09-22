<?php

namespace App\Enums;

/**
 * D-072: exactly three resolutions in stage 2.6 -- no generic per-field
 * merge engine. Field-level merge semantics are entity-specific and not yet
 * defined even for Customer/Product, so MANUAL_EDIT (a human-supplied
 * replacement payload) stands in for "merge" until a real policy exists.
 */
enum SyncConflictResolution: int
{
    case KEEP_SERVER = 1;
    case KEEP_LOCAL = 2;
    case MANUAL_EDIT = 3;

    public function label(): string
    {
        return __(match ($this) {
            self::KEEP_SERVER => 'Keep server version',
            self::KEEP_LOCAL => 'Keep local (device) version',
            self::MANUAL_EDIT => 'Manual edit',
        });
    }
}
