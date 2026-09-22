<?php

namespace App\Enums;

/**
 * P8.1 (Zero-Knowledge UX). 'advanced' is the default for every existing
 * user (see the migration) -- today's full forms/terminology, unchanged.
 * 'simple' opts into plain-business-language, step-by-step flows for
 * screens that have one built (Sell Invoice creation is the first, see
 * P8.2); a screen with no simple-mode treatment yet just renders its
 * normal advanced form regardless of this setting.
 */
enum UiMode: string
{
    case ADVANCED = 'advanced';

    case SIMPLE = 'simple';

    public function label(): string
    {
        return match ($this) {
            self::ADVANCED => __('Advanced (full accounting view)'),
            self::SIMPLE => __('Simple (plain business language)'),
        };
    }

    public static function valueNames(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
