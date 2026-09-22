<?php

namespace App\Enums;

enum SyncConflictStatus: int
{
    case OPEN = 1;
    case RESOLVED = 2;

    public function label(): string
    {
        return __(match ($this) {
            self::OPEN => 'Open',
            self::RESOLVED => 'Resolved',
        });
    }
}
