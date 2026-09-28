<?php

namespace App\Enums;

/**
 * Manufacturing/BOM readiness only (Dakhl_MANUFACTURING_BOM_RESEARCH_AND_PLAN_2026-09-28.md,
 * Phase 0). Purely a classification label -- nothing in the app reads this yet, and every
 * existing Product defaults to GENERAL, so this column changes no behavior today.
 */
enum ProductType: string
{
    case GENERAL = 'general';

    case RAW_MATERIAL = 'raw_material';

    case SEMI_FINISHED = 'semi_finished';

    case FINISHED_GOOD = 'finished_good';

    case WASTE = 'waste';

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => __('General'),
            self::RAW_MATERIAL => __('Raw material'),
            self::SEMI_FINISHED => __('Semi-finished'),
            self::FINISHED_GOOD => __('Finished good'),
            self::WASTE => __('Waste'),
        };
    }

    public static function valueNames(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
