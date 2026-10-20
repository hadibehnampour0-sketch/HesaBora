<?php

namespace App\Services\Financing;

class InstallmentCalculator
{
    /**
     * Calculate Market Monthly Financing Plan (فرمول سود ماهانه بازاری و راس‌گیری سنتی).
     *
     * Example: 10,000,000 Rials principal, 3% monthly rate, 10 months.
     * Total Interest = 10,000,000 * 0.03 * 10 = 3,000,000 Rials.
     * Total Contract = 13,000,000 Rials.
     * Monthly Payment = 1,300,000 Rials.
     */
    public static function calculateMarketPlan(int $principal, float $monthlyRate, int $tenorMonths): array
    {
        if ($tenorMonths <= 0 || $principal <= 0) {
            return [
                'principal' => $principal,
                'total_interest' => 0,
                'total_amount' => $principal,
                'monthly_installment' => $principal,
                'tenor_months' => $tenorMonths,
                'schedule' => [],
            ];
        }

        $totalInterest = (int) round($principal * ($monthlyRate / 100.0) * $tenorMonths);
        $totalAmount = $principal + $totalInterest;
        $monthlyInstallment = (int) round($totalAmount / $tenorMonths);

        $schedule = [];
        $accumulatedPrincipal = 0;
        $accumulatedInterest = 0;

        $basePrincipalPortion = (int) floor($principal / $tenorMonths);
        $baseInterestPortion = (int) floor($totalInterest / $tenorMonths);

        for ($i = 1; $i <= $tenorMonths; $i++) {
            if ($i === $tenorMonths) {
                // Last installment absorbs any fractional roundings
                $pPortion = $principal - $accumulatedPrincipal;
                $iPortion = $totalInterest - $accumulatedInterest;
            } else {
                $pPortion = $basePrincipalPortion;
                $iPortion = $baseInterestPortion;
            }

            $accumulatedPrincipal += $pPortion;
            $accumulatedInterest += $iPortion;

            $schedule[] = [
                'installment_number' => $i,
                'principal_portion' => $pPortion,
                'interest_portion' => $iPortion,
                'amount' => $pPortion + $iPortion,
            ];
        }

        return [
            'principal' => $principal,
            'total_interest' => $totalInterest,
            'total_amount' => $totalAmount,
            'monthly_installment' => $monthlyInstallment,
            'tenor_months' => $tenorMonths,
            'schedule' => $schedule,
        ];
    }

    /**
     * Calculate Standard Banking Compound Amortization Plan (فرمول استاندارد بانکی / اقساط مساوی PMT).
     */
    public static function calculateBankingPlan(int $principal, float $annualRate, int $tenorMonths): array
    {
        if ($tenorMonths <= 0 || $principal <= 0) {
            return [
                'principal' => $principal,
                'total_interest' => 0,
                'total_amount' => $principal,
                'monthly_installment' => $principal,
                'tenor_months' => $tenorMonths,
                'schedule' => [],
            ];
        }

        $monthlyRate = ($annualRate / 100.0) / 12.0;

        if ($monthlyRate <= 0) {
            return self::calculateMarketPlan($principal, 0.0, $tenorMonths);
        }

        // PMT formula: P * (r * (1 + r)^n) / ((1 + r)^n - 1)
        $factor = pow(1 + $monthlyRate, $tenorMonths);
        $pmt = (int) round($principal * ($monthlyRate * $factor) / ($factor - 1));

        $totalAmount = $pmt * $tenorMonths;
        $totalInterest = max(0, $totalAmount - $principal);

        $schedule = [];
        $remainingPrincipal = $principal;
        $accumulatedPrincipal = 0;
        $accumulatedInterest = 0;

        for ($i = 1; $i <= $tenorMonths; $i++) {
            $interestPortion = (int) round($remainingPrincipal * $monthlyRate);

            if ($i === $tenorMonths) {
                $principalPortion = $principal - $accumulatedPrincipal;
                $interestPortion = $totalInterest - $accumulatedInterest;
                $amount = $principalPortion + $interestPortion;
            } else {
                $principalPortion = min($remainingPrincipal, $pmt - $interestPortion);
                $amount = $pmt;
            }

            $remainingPrincipal -= $principalPortion;
            $accumulatedPrincipal += $principalPortion;
            $accumulatedInterest += $interestPortion;

            $schedule[] = [
                'installment_number' => $i,
                'principal_portion' => $principalPortion,
                'interest_portion' => $interestPortion,
                'amount' => $amount,
            ];
        }

        return [
            'principal' => $principal,
            'total_interest' => $totalInterest,
            'total_amount' => $totalAmount,
            'monthly_installment' => $pmt,
            'tenor_months' => $tenorMonths,
            'schedule' => $schedule,
        ];
    }

    /**
     * Calculate overdue late-payment delay penalty (محاسبه خسارت تأخیر تأدیه / جریمه دیرکرد).
     */
    public static function calculateLateFee(int $remainingAmount, int $daysOverdue, float $annualLateFeeRate, int $gracePeriod = 0): int
    {
        if ($remainingAmount <= 0 || $daysOverdue <= $gracePeriod || $annualLateFeeRate <= 0) {
            return 0;
        }

        $effectiveDays = $daysOverdue - $gracePeriod;

        // Daily interest: principal * (annual_rate / 100) * (days / 365)
        return (int) round($remainingAmount * ($annualLateFeeRate / 100.0) * ($effectiveDays / 365.0));
    }
}
