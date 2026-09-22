<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * P3.4 (Multi-Currency Foundation). Deliberately scoped: this is the
 * conversion/FX-difference MATH and the reference data (currencies,
 * historical daily rates) -- it does not itself rewire Invoice/Payment/COGS
 * to be multi-currency-aware. A future feature (multi-currency invoicing)
 * would call convertToBase()/fxDifference() from within Invoice/Payment
 * creation; this class is the reusable foundation those features build on.
 */
class CurrencyService
{
    public function createCurrency(array $data): Currency
    {
        if (! empty($data['is_base'])) {
            Currency::where('is_base', true)->update(['is_base' => false]);
        }

        return Currency::create([
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'symbol' => $data['symbol'] ?? null,
            'is_base' => $data['is_base'] ?? false,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Upserts the rate for a given effective date (one rate per
     * currency/day -- re-recording the same day corrects it rather than
     * creating a duplicate history entry).
     */
    public function recordRate(Currency $currency, float $rate, string $effectiveDate, ?int $userId = null): ExchangeRate
    {
        if ($rate <= 0) {
            throw ValidationException::withMessages([
                'rate' => [__('The exchange rate must be greater than zero.')],
            ]);
        }

        // updateOrCreate()'s own lookup compares the raw string against
        // the 'date' cast's stored (datetime-formatted) value and never
        // matches an existing row -- look up explicitly with whereDate()
        // first instead.
        $existing = ExchangeRate::where('currency_id', $currency->id)
            ->whereDate('effective_date', $effectiveDate)
            ->first();

        if ($existing) {
            $existing->update(['rate' => $rate, 'created_by' => $userId]);

            return $existing->fresh();
        }

        return ExchangeRate::create([
            'currency_id' => $currency->id,
            'effective_date' => $effectiveDate,
            'rate' => $rate,
            'created_by' => $userId,
        ]);
    }

    /**
     * The most recent rate ON OR BEFORE the given date (a currency isn't
     * necessarily re-rated every single day, so a transaction on a
     * non-rated date uses the last known rate, not a missing one).
     */
    public function rateOn(Currency $currency, string $date): float
    {
        if ($currency->is_base) {
            return 1.0;
        }

        $rate = ExchangeRate::where('currency_id', $currency->id)
            ->whereDate('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->value('rate');

        if ($rate === null) {
            throw ValidationException::withMessages([
                'currency' => [__('No exchange rate is recorded for this currency on or before the given date.')],
            ]);
        }

        return (float) $rate;
    }

    public function convertToBase(float $foreignAmount, Currency $currency, string $date): float
    {
        return round($foreignAmount * $this->rateOn($currency, $date), 2);
    }

    /**
     * FX revaluation difference: the base-currency gain or loss realized
     * because a foreign-currency amount is converted at a DIFFERENT rate
     * on settlement than it was on original posting -- e.g. a foreign-
     * currency receivable invoiced at one rate, collected weeks later at
     * another. Positive = gain, negative = loss (receivable side
     * convention; a payable's sign is the caller's to interpret, since
     * "gain" and "loss" flip depending on which side of the transaction
     * you're on).
     *
     * @return array{original_base_amount: float, settlement_base_amount: float, difference: float}
     */
    public function fxDifference(float $foreignAmount, float $originalRate, float $settlementRate): array
    {
        $originalBaseAmount = round($foreignAmount * $originalRate, 2);
        $settlementBaseAmount = round($foreignAmount * $settlementRate, 2);

        return [
            'original_base_amount' => $originalBaseAmount,
            'settlement_base_amount' => $settlementBaseAmount,
            'difference' => round($settlementBaseAmount - $originalBaseAmount, 2),
        ];
    }
}
