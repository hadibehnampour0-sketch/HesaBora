<?php

namespace App\Enums;

enum RecurringFrequency: int
{
    case MONTHLY = 1;
    case QUARTERLY = 2;
    case YEARLY = 3;

    public function label(): string
    {
        return __(match ($this) {
            self::MONTHLY => 'Monthly',
            self::QUARTERLY => 'Quarterly',
            self::YEARLY => 'Yearly',
        });
    }

    /**
     * Number of calendar months this frequency advances per interval unit.
     */
    public function months(): int
    {
        return match ($this) {
            self::MONTHLY => 1,
            self::QUARTERLY => 3,
            self::YEARLY => 12,
        };
    }
}
