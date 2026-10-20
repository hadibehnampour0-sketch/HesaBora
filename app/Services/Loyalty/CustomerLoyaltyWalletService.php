<?php

namespace App\Services\Loyalty;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use App\Models\Invoice;
use App\Models\LoyaltySetting;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerLoyaltyWalletService
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function getSettings(int $companyId): LoyaltySetting
    {
        return LoyaltySetting::firstOrCreate(
            ['company_id' => $companyId],
            [
                'is_enabled' => true,
                'spend_per_point' => 100000,
                'point_value_rials' => 1000,
                'silver_points_threshold' => 500,
                'gold_points_threshold' => 2000,
                'vip_points_threshold' => 5000,
                'silver_discount_percent' => 2.00,
                'gold_discount_percent' => 5.00,
                'vip_discount_percent' => 10.00,
            ]
        );
    }

    public function updateSettings(int $companyId, array $data): LoyaltySetting
    {
        $settings = $this->getSettings($companyId);
        $settings->update($data);
        return $settings->fresh();
    }

    public function getOrCreateWallet(Customer $customer): CustomerWallet
    {
        return CustomerWallet::firstOrCreate(
            [
                'company_id' => $customer->company_id,
                'customer_id' => $customer->id,
            ],
            [
                'balance' => 0,
                'points' => 0,
                'tier' => 'BRONZE',
            ]
        );
    }

    /**
     * Accrues loyalty points for an approved/paid Sell invoice.
     */
    public function accruePointsFromInvoice(Invoice $invoice): ?CustomerWalletTransaction
    {
        if ($invoice->invoice_type !== InvoiceType::SELL) {
            return null;
        }

        if (! $invoice->status->isApprovedOrSettled()) {
            return null;
        }

        if (! $invoice->customer) {
            return null;
        }

        $settings = $this->getSettings($invoice->company_id);
        if (! $settings->is_enabled || $settings->spend_per_point <= 0) {
            return null;
        }

        $spendAmount = (float) $invoice->amount;
        if ($spendAmount <= 0) {
            return null;
        }

        $pointsToAward = (int) floor($spendAmount / $settings->spend_per_point);
        if ($pointsToAward <= 0) {
            return null;
        }

        return DB::transaction(function () use ($invoice, $settings, $pointsToAward) {
            // Idempotency check: don't accrue points twice for the same invoice
            $existing = CustomerWalletTransaction::query()
                ->where('company_id', $invoice->company_id)
                ->where('reference_id', $invoice->id)
                ->where('reference_type', 'Invoice')
                ->where('type', 'POINTS_ACCRUED')
                ->first();

            if ($existing) {
                return $existing;
            }

            $wallet = CustomerWallet::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    [
                        'company_id' => $invoice->company_id,
                        'customer_id' => $invoice->customer_id,
                    ],
                    [
                        'balance' => 0,
                        'points' => 0,
                        'tier' => 'BRONZE',
                    ]
                );

            $wallet->points += $pointsToAward;
            $wallet->recalculateTier($settings);
            $wallet->save();

            return CustomerWalletTransaction::create([
                'company_id' => $invoice->company_id,
                'wallet_id' => $wallet->id,
                'customer_id' => $invoice->customer_id,
                'type' => 'POINTS_ACCRUED',
                'amount' => 0,
                'points_change' => $pointsToAward,
                'balance_after' => $wallet->balance,
                'points_after' => $wallet->points,
                'reference_id' => $invoice->id,
                'reference_type' => 'Invoice',
                'description' => 'امتیاز وفاداری بابت فاکتور فروش شماره ' . $invoice->number,
            ]);
        });
    }

    /**
     * Convert accrued loyalty points into customer wallet store credit.
     */
    public function convertPointsToWalletCredit(Customer $customer, int $points, ?User $user = null): CustomerWalletTransaction
    {
        if ($points <= 0) {
            throw ValidationException::withMessages([
                'points' => [__('تعداد امتیاز برای تبدیل باید بیشتر از صفر باشد.')],
            ]);
        }

        $settings = $this->getSettings($customer->company_id);
        if (! $settings->is_enabled || $settings->point_value_rials <= 0) {
            throw ValidationException::withMessages([
                'points' => [__('تبدیل امتیاز در تنظیمات غیرفعال است.')],
            ]);
        }

        return DB::transaction(function () use ($customer, $points, $settings, $user) {
            $wallet = CustomerWallet::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    ['company_id' => $customer->company_id, 'customer_id' => $customer->id],
                    ['balance' => 0, 'points' => 0, 'tier' => 'BRONZE']
                );

            if ($wallet->points < $points) {
                throw ValidationException::withMessages([
                    'points' => [__('امتیاز مشتری (:have) کمتر از مقدار درخواستی (:want) است.', [
                        'have' => $wallet->points,
                        'want' => $points,
                    ])],
                ]);
            }

            $creditRials = $points * $settings->point_value_rials;

            $wallet->points -= $points;
            $wallet->balance += $creditRials;
            $wallet->recalculateTier($settings);
            $wallet->save();

            return CustomerWalletTransaction::create([
                'company_id' => $customer->company_id,
                'wallet_id' => $wallet->id,
                'customer_id' => $customer->id,
                'type' => 'POINTS_REDEEMED',
                'amount' => $creditRials,
                'points_change' => -$points,
                'balance_after' => $wallet->balance,
                'points_after' => $wallet->points,
                'description' => "تبدیل {$points} امتیاز به " . number_format($creditRials) . ' ریال اعتبار کیف پول',
                'user_id' => $user?->id,
            ]);
        });
    }

    /**
     * Deposit cash/funds directly into store credit wallet.
     */
    public function depositWalletCredit(Customer $customer, int $amount, string $description, ?User $user = null): CustomerWalletTransaction
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => [__('مبلغ شارژ کیف پول باید بیشتر از صفر باشد.')],
            ]);
        }

        return DB::transaction(function () use ($customer, $amount, $description, $user) {
            $wallet = CustomerWallet::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    ['company_id' => $customer->company_id, 'customer_id' => $customer->id],
                    ['balance' => 0, 'points' => 0, 'tier' => 'BRONZE']
                );

            $wallet->balance += $amount;
            $wallet->save();

            return CustomerWalletTransaction::create([
                'company_id' => $customer->company_id,
                'wallet_id' => $wallet->id,
                'customer_id' => $customer->id,
                'type' => 'WALLET_DEPOSIT',
                'amount' => $amount,
                'points_change' => 0,
                'balance_after' => $wallet->balance,
                'points_after' => $wallet->points,
                'description' => $description ?: 'شارژ مستقیم کیف پول مشتری',
                'user_id' => $user?->id,
            ]);
        });
    }

    /**
     * Pay an invoice using available customer wallet store credit.
     */
    public function payInvoiceFromWallet(Invoice $invoice, int $amount, ?User $user = null): CustomerWalletTransaction
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => [__('مبلغ پرداختی از کیف پول باید بیشتر از صفر باشد.')],
            ]);
        }

        if (! $invoice->customer) {
            throw ValidationException::withMessages([
                'invoice_id' => [__('فاکتور مورد نظر مشتری معتبر ندارد.')],
            ]);
        }

        $remaining = $this->paymentService->remainingAmount($invoice);
        if ($amount > $remaining + 0.001) {
            throw ValidationException::withMessages([
                'amount' => [__('مبلغ پرداخت کیف پول (:amount) بیشتر از مانده قابل پرداخت فاکتور (:rem) است.', [
                    'amount' => number_format($amount),
                    'rem' => number_format($remaining),
                ])],
            ]);
        }

        return DB::transaction(function () use ($invoice, $amount, $user) {
            $wallet = CustomerWallet::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    ['company_id' => $invoice->company_id, 'customer_id' => $invoice->customer_id],
                    ['balance' => 0, 'points' => 0, 'tier' => 'BRONZE']
                );

            if ($wallet->balance < $amount) {
                throw ValidationException::withMessages([
                    'amount' => [__('موجودی کیف پول (:bal) برای این پرداخت (:amount) کافی نیست.', [
                        'bal' => number_format($wallet->balance),
                        'amount' => number_format($amount),
                    ])],
                ]);
            }

            $wallet->balance -= $amount;
            $wallet->save();

            // Create Document and Payment row to maintain accounting integrity and invoice sync
            $document = \App\Models\Document::create([
                'company_id' => $invoice->company_id,
                'number' => (\App\Models\Document::withoutGlobalScopes()->where('company_id', $invoice->company_id)->max('number') ?? 0) + 1,
                'date' => now()->toDateString(),
                'title' => 'تسویه از محل اعتبار کیف پول مشتری',
                'creator_id' => $user?->id ?? $invoice->creator_id,
                'approved_at' => now(),
                'approver_id' => $user?->id ?? $invoice->creator_id,
            ]);

            Payment::create([
                'invoice_id' => $invoice->id,
                'document_id' => $document->id,
                'payer_id' => $invoice->customer_id,
                'amount' => $amount,
                'date' => now(),
                'description' => 'تسویه از محل اعتبار کیف پول مشتری',
                'reference_number' => 'WALLET-' . $wallet->id . '-' . time(),
                'creator_id' => $user?->id,
            ]);

            $this->paymentService->syncInvoiceStatus($invoice);

            return CustomerWalletTransaction::create([
                'company_id' => $invoice->company_id,
                'wallet_id' => $wallet->id,
                'customer_id' => $invoice->customer_id,
                'type' => 'INVOICE_PAYMENT',
                'amount' => -$amount,
                'points_change' => 0,
                'balance_after' => $wallet->balance,
                'points_after' => $wallet->points,
                'reference_id' => $invoice->id,
                'reference_type' => 'Invoice',
                'description' => 'پرداخت فاکتور شماره ' . $invoice->number . ' از محل اعتبار کیف پول',
                'user_id' => $user?->id,
            ]);
        });
    }

    /**
     * Get discount percentage for customer's tier.
     */
    public function getTierDiscountPercent(Customer $customer): float
    {
        $wallet = $this->getOrCreateWallet($customer);
        $settings = $this->getSettings($customer->company_id);

        if (! $settings->is_enabled) {
            return 0.0;
        }

        return match ($wallet->tier) {
            'VIP' => (float) $settings->vip_discount_percent,
            'GOLD' => (float) $settings->gold_discount_percent,
            'SILVER' => (float) $settings->silver_discount_percent,
            default => 0.0,
        };
    }
}
