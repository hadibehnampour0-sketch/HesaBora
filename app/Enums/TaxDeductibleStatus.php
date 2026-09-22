<?php

namespace App\Enums;

/**
 * P9.5 (Expense tax-deductibility classification). AncillaryCost is this
 * app's closest existing concept to a standalone "expense" record (no
 * generic Expense model exists anywhere in the codebase, confirmed by
 * audit) -- this classification is additive to it, not a new document
 * type.
 */
enum TaxDeductibleStatus: string
{
    case DEDUCTIBLE = 'deductible';

    case NON_DEDUCTIBLE = 'non_deductible';

    case NEEDS_REVIEW = 'needs_review';

    public function label(): string
    {
        return match ($this) {
            self::DEDUCTIBLE => __('Deductible'),
            self::NON_DEDUCTIBLE => __('Non-deductible'),
            self::NEEDS_REVIEW => __('Needs review'),
        };
    }

    public static function valueNames(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
