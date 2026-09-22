<?php

namespace App\Enums;

enum DailySettlementStatus: string
{
    case DRAFT = 'draft';

    case SUBMITTED = 'submitted';

    case APPROVED = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::SUBMITTED => __('Submitted'),
            self::APPROVED => __('Approved'),
        };
    }

    public static function valueNames(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
