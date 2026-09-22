<?php

namespace App\Services;

use App\Models\TaxRule;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * P9.2 (Tax Rules engine). Mirrors `CurrencyService::rateOn()` (P3.4)
 * exactly: a row per validity period, looked up "as of" a given date,
 * most-specific-then-most-recent wins, and no silent fallback to a
 * guessed default -- an unconfigured rate is a configuration error that
 * must be surfaced, not masked.
 */
class TaxRuleService
{
    public function recordRule(?string $sstid, float $rate, bool $isExempt, string $effectiveFrom, ?string $effectiveTo, ?string $description, ?TaxRule $existing = null): TaxRule
    {
        $data = [
            'sstid' => $sstid,
            'rate' => $rate,
            'is_exempt' => $isExempt,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'description' => $description,
        ];

        if ($existing) {
            $existing->update($data);

            return $existing->fresh();
        }

        return TaxRule::create($data);
    }

    /**
     * The applicable VAT rate (a percentage, e.g. 10.0) for a given
     * Moadian `sstid` tax category as of a given date. `$sstid = null`
     * looks up the general/default rate. Falls back from a
     * category-specific rule to the general rule if no category-specific
     * one is in effect on that date.
     */
    public function vatRateOn(?string $sstid, Carbon $date): float
    {
        $rule = $this->resolveRuleOn($sstid, $date);

        return $rule->is_exempt ? 0.0 : $rule->rate;
    }

    public function isExemptOn(?string $sstid, Carbon $date): bool
    {
        return $this->resolveRuleOn($sstid, $date)->is_exempt;
    }

    private function resolveRuleOn(?string $sstid, Carbon $date): TaxRule
    {
        $dateString = $date->toDateString();

        if ($sstid !== null) {
            $specific = TaxRule::where('sstid', $sstid)
                ->where('effective_from', '<=', $dateString)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $dateString))
                ->orderByDesc('effective_from')
                ->first();

            if ($specific) {
                return $specific;
            }
        }

        $general = TaxRule::whereNull('sstid')
            ->where('effective_from', '<=', $dateString)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $dateString))
            ->orderByDesc('effective_from')
            ->first();

        if (! $general) {
            throw ValidationException::withMessages([
                'sstid' => [__('No tax rule is configured for :date. Please record a general VAT rate before this date.', ['date' => $dateString])],
            ]);
        }

        return $general;
    }
}
