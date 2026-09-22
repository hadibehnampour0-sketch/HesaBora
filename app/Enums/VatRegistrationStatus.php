<?php

namespace App\Enums;

/**
 * P9.1 (Tax Profile). Iran VAT law framework: a business is either not yet
 * registered with the VAT system, fully registered (must charge/remit
 * VAT), or exempt (small-business threshold or an exempt activity type --
 * still may need to file, but charges no VAT on sales).
 */
enum VatRegistrationStatus: string
{
    case NOT_REGISTERED = 'not_registered';

    case REGISTERED = 'registered';

    case EXEMPT = 'exempt';

    public function label(): string
    {
        return match ($this) {
            self::NOT_REGISTERED => __('Not registered'),
            self::REGISTERED => __('Registered'),
            self::EXEMPT => __('Exempt'),
        };
    }

    public static function valueNames(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
