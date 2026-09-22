<?php

namespace App\Enums;

/**
 * P11 (Field Sales). `rules` (JSON, on the `Promotion` model) shape
 * depends on this type:
 *
 * TIERED_DISCOUNT: {"product_id": int|null, "product_group_id": int|null,
 *   "tiers": [{"min_qty": number, "discount_percent": number}, ...]}
 *   `product_id`/`product_group_id` both null means "applies to the whole
 *   cart's total quantity," not a specific item.
 *
 * BONUS_GOODS: {"buy_product_id": int, "buy_qty": number,
 *   "free_product_id": int, "free_qty": number}
 */
enum PromotionType: string
{
    case TIERED_DISCOUNT = 'tiered_discount';

    case BONUS_GOODS = 'bonus_goods';

    public function label(): string
    {
        return match ($this) {
            self::TIERED_DISCOUNT => __('Tiered discount'),
            self::BONUS_GOODS => __('Bonus goods'),
        };
    }

    public static function valueNames(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
