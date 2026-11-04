<?php

namespace App\Services\Financing;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Document;
use App\Models\FinancingPlan;
use App\Models\InstallmentCollateral;
use App\Models\InstallmentContract;
use App\Models\InstallmentSchedule;
use App\Models\Subject;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

        $appliedRate = $method === 'BANKING_AMORTIZATION' ? $annualRate : $monthlyRate;

        return DB::transaction(function () use ($data, $companyId, $user, $totalPrice, $downPayment, $principal, $calc, $tenorMonths, $startDate, $plan, $method, $appliedRate) {
            $contractNumber = $data['contract_number'] ?? ('INS-'.date('Ymd').'-'.rand(1000, 9999));

            $contract = InstallmentContract::create([
                'company_id' => $companyId,
                'contract_number' => $contractNumber,
                'customer_id' => $data['customer_id'],
                'invoice_id' => $data['invoice_id'] ?? null,
                'plan_id' => $plan?->id,
                'calculation_method' => $method,
                'applied_interest_rate' => $appliedRate,
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

            // Create initial collaterals if provided in payload
            if (! empty($data['collaterals']) && is_array($data['collaterals'])) {
                foreach ($data['collaterals'] as $colData) {
                    if (is_array($colData) && ! empty($colData['type'])) {
                        $this->addCollateral($contract, $colData);
                    }
                }
            }

            return $contract->fresh(['schedules', 'collaterals']);
        });
    }

    /**
     * Record installment payment collection.
     */
    public function payInstallment(
        InstallmentSchedule $schedule,
        int $amount,
        ?User $user = null,
        ?int $receivedSubjectId = null
    ): InstallmentSchedule {
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

        return DB::transaction(function () use ($schedule, $amount, $user, $receivedSubjectId) {
            $schedule->paid_amount += $amount;

            if ($schedule->remainingAmount() <= 0) {
                $schedule->status = 'PAID';
                $schedule->paid_at = now();
            } else {
                $schedule->status = 'PARTIALLY_PAID';
            }

            // Create balanced accounting Document & Transactions (D7: SUM(transactions.value) = 0)
            $customer = $schedule->contract->customer;
            $customerSubjectId = $customer?->subject_id
                ?? $customer?->subject?->id
                ?? Subject::where('company_id', $schedule->company_id)->where('code', config('amir.cust_subject'))->value('id')
                ?? Subject::where('company_id', $schedule->company_id)->value('id')
                ?? (Subject::first()?->id);

            $settlementSubjectId = $receivedSubjectId;
            if (! $settlementSubjectId) {
                $bankAccount = BankAccount::where('company_id', $schedule->company_id)->whereNotNull('subject_id')->first();
                $settlementSubjectId = $bankAccount?->subject_id
                    ?? Subject::where('company_id', $schedule->company_id)->whereIn('code', [config('amir.cash'), config('amir.bank'), '101', '102'])->value('id')
                    ?? Subject::where('company_id', $schedule->company_id)->where('id', '!=', $customerSubjectId)->value('id')
                    ?? Subject::where('company_id', $schedule->company_id)->value('id')
                    ?? (Subject::first()?->id);
            }

            $desc = "دریافت قسط شماره {$schedule->installment_number} قرارداد {$schedule->contract->contract_number}";
            $transactions = [
                [
                    'subject_id' => $settlementSubjectId,
                    'desc' => $desc,
                    'value' => -$amount, // Debit Bank/Cash (negative in Dakhl convention)
                ],
                [
                    'subject_id' => $customerSubjectId,
                    'desc' => $desc,
                    'value' => $amount, // Credit Customer (positive in Dakhl convention)
                ],
            ];

            $effectiveUser = $user ?? $schedule->contract->creator ?? User::first();
            if ($effectiveUser) {
                $document = DocumentService::createDocument($effectiveUser, [
                    'company_id' => $schedule->company_id,
                    'number' => null,
                    'date' => now()->toDateString(),
                    'title' => $desc,
                ], $transactions);

                DocumentService::changeDocumentStatus($document, $effectiveUser, 'approved');
                $schedule->document_id = $document->id;
            }

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

        $collaterals = $contract->collaterals;
        $totalCollateralsCount = $collaterals->count();
        $activeCollateralsCount = $collaterals->where('status', 'RECEIVED')->count();
        $totalCollateralsValue = (int) $collaterals->where('status', 'RECEIVED')->sum('estimated_value');

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
            'calculation_method' => $contract->calculation_method,
            'applied_interest_rate' => $contract->applied_interest_rate,
            'total_collaterals_count' => $totalCollateralsCount,
            'active_collaterals_count' => $activeCollateralsCount,
            'total_collaterals_value' => $totalCollateralsValue,
        ];
    }

    /**
     * Add a collateral (cheque, gold, property, vehicle, promissory note) to an installment contract.
     */
    public function addCollateral(InstallmentContract $contract, array $data, ?UploadedFile $file = null): InstallmentCollateral
    {
        $attachmentPath = null;
        if ($file && $file->isValid()) {
            $attachmentPath = $file->store('financing/collaterals', 'public');
        } elseif (! empty($data['attachment_path'])) {
            $attachmentPath = $data['attachment_path'];
        }

        $type = $data['type'] ?? 'OTHER';
        $title = $data['title'] ?? null;
        if (! $title) {
            $title = match ($type) {
                'CHEQUE' => 'چک صیادی ' . ($data['cheque_sayad_number'] ?? ''),
                'GOLD' => 'طلای وثیقه (' . ($data['gold_weight_grams'] ?? 0) . ' گرم)',
                'PROPERTY' => 'ملک پلاک ' . ($data['property_registry_code'] ?? ''),
                'VEHICLE' => 'خودرو ' . ($data['vehicle_model'] ?? '') . ' ' . ($data['vehicle_plate'] ?? ''),
                'PROMISSORY_NOTE' => 'سفته شماره ' . ($data['promissory_note_number'] ?? ''),
                default => 'وثیقه ضمانت اقساط',
            };
        }

        return InstallmentCollateral::create([
            'company_id' => $contract->company_id,
            'contract_id' => $contract->id,
            'type' => $type,
            'title' => $title,
            'purpose' => $data['purpose'] ?? 'GUARANTEE',
            'estimated_value' => isset($data['estimated_value']) ? (int) $data['estimated_value'] : null,
            'status' => $data['status'] ?? 'RECEIVED',
            'cheque_sayad_number' => $data['cheque_sayad_number'] ?? null,
            'cheque_bank' => $data['cheque_bank'] ?? null,
            'cheque_account_owner' => $data['cheque_account_owner'] ?? null,
            'cheque_due_date' => $data['cheque_due_date'] ?? null,
            'cheque_amount' => isset($data['cheque_amount']) ? (int) $data['cheque_amount'] : null,
            'cheque_installment_number' => isset($data['cheque_installment_number']) ? (int) $data['cheque_installment_number'] : null,
            'gold_weight_grams' => isset($data['gold_weight_grams']) ? (float) $data['gold_weight_grams'] : null,
            'gold_karat' => isset($data['gold_karat']) ? (int) $data['gold_karat'] : 18,
            'property_registry_code' => $data['property_registry_code'] ?? null,
            'property_address' => $data['property_address'] ?? null,
            'property_area' => isset($data['property_area']) ? (float) $data['property_area'] : null,
            'vehicle_model' => $data['vehicle_model'] ?? null,
            'vehicle_plate' => $data['vehicle_plate'] ?? null,
            'vehicle_chassis_number' => $data['vehicle_chassis_number'] ?? null,
            'vehicle_color' => $data['vehicle_color'] ?? null,
            'promissory_note_number' => $data['promissory_note_number'] ?? null,
            'attachment_path' => $attachmentPath,
            'notes' => $data['notes'] ?? null,
            'received_at' => $data['received_at'] ?? now()->toDateString(),
        ]);
    }

    /**
     * Release a collateral back to the customer upon full settlement or replacement.
     */
    public function releaseCollateral(InstallmentCollateral $collateral, ?string $releaseNotes = null): InstallmentCollateral
    {
        $collateral->update([
            'status' => 'RELEASED',
            'released_at' => now()->toDateString(),
            'release_notes' => $releaseNotes,
        ]);

        return $collateral->fresh();
    }

    /**
     * Delete a collateral and its attachment file.
     */
    public function deleteCollateral(InstallmentCollateral $collateral): bool
    {
        if ($collateral->attachment_path && Storage::disk('public')->exists($collateral->attachment_path)) {
            Storage::disk('public')->delete($collateral->attachment_path);
        }

        return (bool) $collateral->delete();
    }
}
