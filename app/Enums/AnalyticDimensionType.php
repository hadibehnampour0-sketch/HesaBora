<?php

namespace App\Enums;

enum AnalyticDimensionType: int
{
    case BRANCH = 1;
    case PROJECT = 2;
    case COST_CENTER = 3;
    case SALES_CHANNEL = 4;

    public function label(): string
    {
        return __(match ($this) {
            self::BRANCH => 'Branch',
            self::PROJECT => 'Project',
            self::COST_CENTER => 'Cost Center',
            self::SALES_CHANNEL => 'Sales Channel',
        });
    }

    public function valueName(): string
    {
        return strtolower($this->name);
    }

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
