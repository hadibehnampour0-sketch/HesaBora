<?php

namespace App\Enums;

enum PeriodStatus: string
{
    case OPEN = 'open';
    case REVIEW = 'review';
    case RECONCILED = 'reconciled';
    case CLOSED = 'closed';
    case LOCKED = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'دوره جاری (باز)',
            self::REVIEW => 'درحال بررسی و انطباق',
            self::RECONCILED => 'مغایرت‌گیری و تسویه شده',
            self::CLOSED => 'بسته شده (اسناد پایانی صادر شده)',
            self::LOCKED => 'قفل قطعی (بایگانی مالیاتی)',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::OPEN => 'badge-success',
            self::REVIEW => 'badge-warning',
            self::RECONCILED => 'badge-info',
            self::CLOSED => 'badge-secondary',
            self::LOCKED => 'badge-error',
        };
    }

    public function isMutable(): bool
    {
        return $this === self::OPEN || $this === self::REVIEW || $this === self::RECONCILED;
    }
}
