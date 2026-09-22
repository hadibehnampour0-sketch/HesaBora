<?php

namespace App\Enums;

enum SerialNumberStatus: int
{
    case IN_STOCK = 1;
    case SOLD = 2;

    public function label(): string
    {
        return __(match ($this) {
            self::IN_STOCK => 'In stock',
            self::SOLD => 'Sold',
        });
    }
}
