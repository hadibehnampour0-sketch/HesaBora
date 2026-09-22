<?php

namespace App\Enums;

enum VisitPlanStatus: string
{
    case PLANNED = 'planned';

    case IN_PROGRESS = 'in_progress';

    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::PLANNED => __('Planned'),
            self::IN_PROGRESS => __('In progress'),
            self::COMPLETED => __('Completed'),
        };
    }

    public static function valueNames(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
