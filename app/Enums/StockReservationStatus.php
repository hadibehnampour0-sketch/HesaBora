<?php

namespace App\Enums;

enum StockReservationStatus: int
{
    case ACTIVE = 1;
    case CONSUMED = 2;
    case RELEASED = 3;

    public function label(): string
    {
        return __(match ($this) {
            self::ACTIVE => 'Active',
            self::CONSUMED => 'Consumed',
            self::RELEASED => 'Released',
        });
    }
}
