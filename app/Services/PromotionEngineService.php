<?php

namespace App\Services;

use App\Enums\PromotionType;
use App\Models\Promotion;
use Illuminate\Support\Carbon;

/**
 * P11 (Field Sales). A pure calculator: given a cart, returns SUGGESTED
 * discounts/bonus items -- it never creates, mutates, or touches an
 * Invoice/Document/Transaction. Applying a suggestion into a real invoice
 * still goes through the existing, completely unchanged Invoice creation
 * flow (the rep/UI copies the suggested `off`/bonus line into the normal
 * invoice form) -- deliberately additive, mirroring the P4.3 principle of
 * never entangling a new subsystem with an already-tested critical path.
 *
 * @phpstan-type CartLine array{product_id: int, quantity: float}
 */
class PromotionEngineService
{
    /**
     * @param  array<int, array{product_id: int, quantity: float}>  $cartLines
     * @return array{
     *   discounts: array<int, array{product_id: int, discount_percent: float, promotion_id: int, promotion_title: string}>,
     *   bonus_items: array<int, array{product_id: int, quantity: float, promotion_id: int, promotion_title: string}>
     * }
     */
    public function evaluate(?int $customerGroupId, array $cartLines, ?Carbon $date = null): array
    {
        $date ??= Carbon::today();

        $applicable = Promotion::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('customer_group_id')->orWhere('customer_group_id', $customerGroupId))
            ->get()
            ->filter(fn (Promotion $p) => $p->isActiveOn($date));

        $result = ['discounts' => [], 'bonus_items' => []];

        foreach ($applicable as $promotion) {
            match ($promotion->type) {
                PromotionType::TIERED_DISCOUNT => $this->evaluateTieredDiscount($promotion, $cartLines, $result),
                PromotionType::BONUS_GOODS => $this->evaluateBonusGoods($promotion, $cartLines, $result),
            };
        }

        return $result;
    }

    private function evaluateTieredDiscount(Promotion $promotion, array $cartLines, array &$result): void
    {
        $rules = $promotion->rules;
        $productId = $rules['product_id'] ?? null;
        $productGroupId = $rules['product_group_id'] ?? null;
        $tiers = $rules['tiers'] ?? [];

        if (empty($tiers)) {
            return;
        }

        // No product/group scope -> applies to the whole cart's quantity.
        $matchingLines = ($productId === null && $productGroupId === null)
            ? $cartLines
            : array_filter($cartLines, function ($line) use ($productId, $productGroupId) {
                if ($productId !== null) {
                    return (int) ($line['product_id'] ?? null) === (int) $productId;
                }

                $product = \App\Models\Product::find($line['product_id'] ?? null);

                return $product && (int) $product->group === (int) $productGroupId;
            });

        $qty = array_sum(array_column($matchingLines, 'quantity'));
        if ($qty <= 0) {
            return;
        }

        // Highest qualifying tier wins.
        $bestTier = null;
        foreach ($tiers as $tier) {
            if ($qty >= (float) ($tier['min_qty'] ?? PHP_INT_MAX)) {
                if ($bestTier === null || $tier['min_qty'] > $bestTier['min_qty']) {
                    $bestTier = $tier;
                }
            }
        }

        if ($bestTier === null) {
            return;
        }

        foreach ($matchingLines as $line) {
            $result['discounts'][] = [
                'product_id' => (int) $line['product_id'],
                'discount_percent' => (float) $bestTier['discount_percent'],
                'promotion_id' => $promotion->id,
                'promotion_title' => $promotion->title,
            ];
        }
    }

    private function evaluateBonusGoods(Promotion $promotion, array $cartLines, array &$result): void
    {
        $rules = $promotion->rules;
        $buyProductId = $rules['buy_product_id'] ?? null;
        $buyQty = (float) ($rules['buy_qty'] ?? 0);
        $freeProductId = $rules['free_product_id'] ?? null;
        $freeQty = (float) ($rules['free_qty'] ?? 0);

        if (! $buyProductId || $buyQty <= 0 || ! $freeProductId || $freeQty <= 0) {
            return;
        }

        $purchasedQty = array_sum(array_map(
            fn ($line) => (int) ($line['product_id'] ?? null) === (int) $buyProductId ? (float) $line['quantity'] : 0,
            $cartLines
        ));

        $multiples = intdiv((int) floor($purchasedQty), (int) $buyQty);
        if ($multiples <= 0) {
            return;
        }

        $result['bonus_items'][] = [
            'product_id' => (int) $freeProductId,
            'quantity' => $multiples * $freeQty,
            'promotion_id' => $promotion->id,
            'promotion_title' => $promotion->title,
        ];
    }
}
