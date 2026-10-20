<?php

namespace App\Services\Financing;

use App\Models\Customer;
use App\Models\Document;
use App\Models\FinancingPlan;
use App\Models\InstallmentContract;
use App\Models\InstallmentSchedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancingService
{
    /**
     * Create an installment contract and generate its amortization schedule.
     */
    public function createContract(array $data, ?User $user = null): InstallmentContract
    {
        $companyId = $data['company_id'];
        $totalPrice = (int) $data['total_price'];
        $downPayment = (int) ($data['down_payment'] ?? 0);
        $tenorMonths = (int) $data['tenor_months'];
        $startDate = Carbon::parse($data['start_date'] ?? now());

        if ($totalPrice <= 0) {
            throw ValidationException::withMessages([
                'total_price' => [__('مبلغ کل کالا/فاکتور باید بیشتر از صفر باشد.')],
            ]);
        }

        if ($downPayment >= $totalPrice) {
            throw ValidationException::withMessages([
                'down_payment' => [__('مبلغ پیش‌پرداخت نمی‌تواند بیشتر یا مساوی کل مبلغ باشد.')],
            ]);
        }

        $principal = $totalPrice - $downPayment;
        $plan = isset($data['plan_id']) ? FinancingPlan::find($data['plan_id']) : null;

        $method = $data['calculation_method'] ?? $plan?->calculation_method ?? 'MARKET_MONTHLY';
        $monthlyRate = (float) ($data['monthly_interest_rate'] ?? $plan?->monthly_interest_rate ?? 3.0);
        $annualRate = (float) ($data['annual_interest_rate'] ?? $plan?->annual_interest_rate ?? 23.0);

        if ($method === 'BANKING_AMORTIZATION') {
            $calc = InstallmentCalculator::calculateBankingPlan($principal, $annualRate, $tenorMonths);
        } else {
            $calc = InstallmentCalculator::calculateMarketPlan($principal, $monthlyRate, $tenorMonths);
        }

        return DB::transaction(function () use ($data, $companyId, $user, $totalPrice, $downPayment, $principal, $calc, $tenorMonths, $startDate, $plan) {
            $contractNumber = $data['contract_number'] ?? ('INS-' . date('Ymd') . '-' . rand(1000, 9999));

            $contract = InstallmentContract::create([
                'company_id' => $companyId,
                'contract_number' => $contractNumber,
                'customer_id' => $data['customer_id'],
                'invoice_id' => $data['invoice_id'] ?? null,
                'plan_id' => $plan?->id,
                'total_price' => $totalPrice,
                'down_payment' => $downPayment,
                'principal_amount' => $principal,
                'total_interest' => $calc['total_interest'],
                'total_amount' => $calc['total_amount'],
                'tenor_months' => $tenorMonths,
                'monthly_installment_amount' => $calc['monthly_installment'],
                'start_date' => $startDate->toDateString(),
                'status' => 'ACTIVE',
                'interest_income_subject_id' => $data['interest_income_subject_id'] ?? null,
                'penalty_income_subject_id' => $data['penalty_income_subject_id'] ?? null,
                'creator_id' => $user?->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($calc['schedule'] as $item) {
                $dueDate = $startDate->copy()->addMonths($item['installment_number']);

                InstallmentSchedule::create([
                    'contract_id' => $contract->id,
                    'company_id' => $companyId,
                    'installment_number' => $item['installment_number'],
                    'due_date' => $dueDate->toDateString(),
                    'principal_portion' => $item['principal_portion'],
                    'interest_portion' => $item['interest_portion'],
                    'amount' => $item['amount'],
                    'paid_amount' => 0,
                    'late_fee_amount' => 0,
                    'status' => 'UNPAID',
                ]);
            }

            return $contract->fresh('schedules');
        });
    }

    /**
     * Record installment payment collection.
     */
    public function payInstallment(InstallmentSchedule $schedule, int $amount, ?User $user = null): InstallmentSchedule
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => [__('مبلغ پرداختی قسط باید بیشتر از صفر باشد.')],
            ]);
        }

        $remaining = $schedule->remainingAmount();
        if ($amount > $remaining) {
            throw ValidationException::withMessages([
                'amount' => [__('مبلغ پرداختی (:amount) بیشتر از مانده قسط (:rem) است.', [
                    'amount' => number_format($amount),
                    'rem' => number_format($remaining),
                ])],
            ]);
        }

        return DB::transaction(function () use ($schedule, $amount, $user) {
            $schedule->paid_amount += $amount;

            if ($schedule->remainingAmount() <= 0) {
                $schedule->status = 'PAID';
                $schedule->paid_at = now();
            } else {
                $schedule->status = 'PARTIALLY_PAID';
            }

            // Create balanced accounting Document for collection
            $document = Document::create([
                'company_id' => $schedule->company_id,
                'number' => (Document::withoutGlobalScopes()->where('company_id', $schedule->company_id)->max('number') ?? 0) + 1,
                'date' => now()->toDateString(),
                'title' => "دریافت قسط شماره {$schedule->installment_number} قرارداد {$schedule->contract->contract_number}",
                'creator_id' => $user?->id,
                'approved_at' => now(),
                'approver_id' => $user?->id,
            ]);

            $schedule->document_id = $document->id;
            $schedule->save();

            // Check if all installments for this contract are now settled
            $hasUnpaid = InstallmentSchedule::query()
                ->where('contract_id', $schedule->contract_id)
                ->where('status', '!=', 'PAID')
                ->exists();

            if (! $hasUnpaid) {
                $schedule->contract->update(['status' => 'SETTLED']);
            }

            return $schedule->fresh();
        });
    }

    /**
     * Calculate and apply overdue delay damages across contract installments.
     */
    public function calculateAndApplyOverduePenalties(InstallmentContract $contract, ?Carbon $asOfDate = null): int
    {
        $asOfDate = $asOfDate ?? now();
        $plan = $contract->plan;
        $lateFeeRate = (float) ($plan?->late_fee_rate_percent ?? 6.0);
        $graceDays = (int) ($plan?->grace_period_days ?? 5);

        $totalPenalties = 0;

        $unpaidOverdueSchedules = $contract->schedules()
            ->where('due_date', '<', $asOfDate->toDateString())
            ->where('status', '!=', 'PAID')
            ->get();

        foreach ($unpaidOverdueSchedules as $schedule) {
            $dueDate = Carbon::parse($schedule->due_date);
            $daysOverdue = (int) $dueDate->diffInDays($asOfDate);

            $penalty = InstallmentCalculator::calculateLateFee(
                $schedule->amount - $schedule->paid_amount,
                $daysOverdue,
                $lateFeeRate,
                $graceDays
            );

            if ($penalty > 0) {
                $schedule->late_fee_amount = $penalty;
                $schedule->status = 'OVERDUE';
                $schedule->save();
                $totalPenalties += $penalty;
            }
        }

        return $totalPenalties;
    }

    /**
     * Get analytical contract metrics summary.
     */
    public function getContractSummary(InstallmentContract $contract): array
    {
        $schedules = $contract->schedules;

        $totalPaid = $schedules->sum('paid_amount');
        $totalLateFees = $schedules->sum('late_fee_amount');
        $totalContractDebt = $contract->total_amount + $totalLateFees;
        $remaining = max(0, $totalContractDebt - $totalPaid);

        $paidCount = $schedules->where('status', 'PAID')->count();
        $overdueCount = $schedules->where('status', 'OVERDUE')->count();

        return [
            'total_price' => $contract->total_price,
            'down_payment' => $contract->down_payment,
            'principal' => $contract->principal_amount,
            'total_interest' => $contract->total_interest,
            'total_contract' => $contract->total_amount,
            'total_paid' => $totalPaid,
            'total_late_fees' => $totalLateFees,
            'remaining_balance' => $remaining,
            'tenor_months' => $contract->tenor_months,
            'paid_installments_count' => $paidCount,
            'overdue_installments_count' => $overdueCount,
            'status' => $contract->status,
        ];
    }
}
