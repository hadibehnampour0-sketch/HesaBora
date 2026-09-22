<?php

namespace App\Enums;

enum FixedAssetStatus: int
{
    case ACTIVE = 1;
    case DISPOSED = 2;

    public function label(): string
    {
        return __(match ($this) {
            self::ACTIVE => 'Active',
            self::DISPOSED => 'Disposed',
        });
    }
}
