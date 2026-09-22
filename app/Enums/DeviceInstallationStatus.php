<?php

namespace App\Enums;

enum DeviceInstallationStatus: int
{
    case UNPAIRED = 1;
    case ACTIVE = 2;
    case REVOKED = 3;

    public function label(): string
    {
        return __(match ($this) {
            self::UNPAIRED => 'Unpaired',
            self::ACTIVE => 'Active',
            self::REVOKED => 'Revoked',
        });
    }
}
