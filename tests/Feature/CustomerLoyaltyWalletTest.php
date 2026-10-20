<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerWallet;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Loyalty\CustomerLoyaltyWalletService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class CustomerLoyaltyWalletTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;
    protected Company $company;
    protected Customer $customer;
    protected CustomerLoyaltyWalletService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::firstOrCreate(['id' => 1], [
            'name' => 'Test Company',
            'fiscal_year' => 1405,
        ]);

        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);

        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'loyalty.*']));

        Cache::forever('active_company_id', $this->company->id);
        Cookie::queue('active-company-id', (string) $this->company->id);
        $_COOKIE['active-company-id'] = (string) $this->company->id;

        $this->importSubjects($this->company->id);
        $this->importConfigs($this->company->id);

        $group = CustomerGroup::factory()->withSubject()->create([
            'name' => 'General',
            'company_id' => $this->company->id,
        ]);

        $this->customer = Customer::factory()->withGroup($group)->withSubject()->create([
            'company_id' => $this->company->id,
            'name' => 'مشتری وفادار',
            'mobile' => '09121112233',
        ]);

        $this->service = app(CustomerLoyaltyWalletService::class);
    }

    public function test_loyalty_settings_defaults_and_update(): void
    {
        $settings = $this->service->getSettings($this->company->id);

        $this->assertTrue($settings->is_enabled);
        $this->assertSame(100000, $settings->spend_per_point);
        $this->assertSame(1000, $settings->point_value_rials);

        $response = $this->actingAs($this->user)->post(route('loyalty.settings.update'), [
            'is_enabled' => 1,
            'spend_per_point' => 50000,
            'point_value_rials' => 2000,
            'silver_points_threshold' => 300,
            'gold_points_threshold' => 1500,
            'vip_points_threshold' => 4000,
            'silver_discount_percent' => 3.0,
            'gold_discount_percent' => 7.0,
            'vip_discount_percent' => 12.0,
        ]);

        $response->assertRedirect(route('loyalty.index'));
        $settings->refresh();

        $this->assertSame(50000, $settings->spend_per_point);
        $this->assertSame(2000, $settings->point_value_rials);
        $this->assertSame(3.0, (float) $settings->silver_discount_percent);
    }

    public function test_points_accrual_on_sell_invoice_and_idempotency(): void
    {
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED,
            'amount' => 5000000, // 5,000,000 Rials -> 50 points (at 100k per point)
            'final_amount' => 5000000,
            'vat' => 0,
            'number' => 101,
            'date' => '2026-10-20',
            'creator_id' => $this->user->id,
        ]);

        $tx = $this->service->accruePointsFromInvoice($invoice);

        $this->assertNotNull($tx);
        $this->assertSame(50, $tx->points_change);

        $wallet = $this->service->getOrCreateWallet($this->customer);
        $this->assertSame(50, $wallet->points);

        // Idempotency: second call returns the existing transaction without duplicating points
        $tx2 = $this->service->accruePointsFromInvoice($invoice);
        $this->assertSame($tx->id, $tx2->id);

        $wallet->refresh();
        $this->assertSame(50, $wallet->points);
    }

    public function test_tier_advancement_from_points(): void
    {
        $wallet = $this->service->getOrCreateWallet($this->customer);
        $settings = $this->service->getSettings($this->company->id);

        // BRONZE tier
        $wallet->update(['points' => 100]);
        $wallet->recalculateTier($settings);
        $this->assertSame('BRONZE', $wallet->tier);
        $this->assertSame(0.0, $this->service->getTierDiscountPercent($this->customer));

        // SILVER tier (threshold 500)
        $wallet->update(['points' => 600]);
        $wallet->recalculateTier($settings);
        $this->assertSame('SILVER', $wallet->tier);
        $this->assertSame(2.0, $this->service->getTierDiscountPercent($this->customer));

        // GOLD tier (threshold 2000)
        $wallet->update(['points' => 2500]);
        $wallet->recalculateTier($settings);
        $this->assertSame('GOLD', $wallet->tier);
        $this->assertSame(5.0, $this->service->getTierDiscountPercent($this->customer));

        // VIP tier (threshold 5000)
        $wallet->update(['points' => 6000]);
        $wallet->recalculateTier($settings);
        $this->assertSame('VIP', $wallet->tier);
        $this->assertSame(10.0, $this->service->getTierDiscountPercent($this->customer));
    }

    public function test_points_conversion_to_wallet_store_credit(): void
    {
        $wallet = $this->service->getOrCreateWallet($this->customer);
        $wallet->update(['points' => 500, 'balance' => 0]);

        // Convert 200 points -> 200 * 1000 = 200,000 Rials credit
        $tx = $this->service->convertPointsToWalletCredit($this->customer, 200, $this->user);

        $this->assertSame(-200, $tx->points_change);
        $this->assertSame(200000, $tx->amount);

        $wallet->refresh();
        $this->assertSame(300, $wallet->points);
        $this->assertSame(200000, $wallet->balance);

        // Rejection when requesting more points than available
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->service->convertPointsToWalletCredit($this->customer, 1000, $this->user);
    }

    public function test_direct_wallet_deposit(): void
    {
        $tx = $this->service->depositWalletCredit($this->customer, 1000000, 'واریز نقدی پیش‌دریافت', $this->user);

        $this->assertSame(1000000, $tx->amount);
        $wallet = $this->service->getOrCreateWallet($this->customer);
        $this->assertSame(1000000, $wallet->balance);

        $this->assertDatabaseHas('customer_wallet_transactions', [
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'type' => 'WALLET_DEPOSIT',
            'amount' => 1000000,
        ]);
    }

    public function test_pay_invoice_using_wallet_balance_and_sync_status(): void
    {
        $wallet = $this->service->getOrCreateWallet($this->customer);
        $wallet->update(['balance' => 500000]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED,
            'amount' => 400000,
            'final_amount' => 400000,
            'vat' => 0,
            'number' => 202,
            'date' => '2026-10-20',
            'creator_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('loyalty.invoices.pay-with-wallet', $invoice->id), [
            'amount' => 400000,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $wallet->refresh();
        $this->assertSame(100000, $wallet->balance);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PAID, $invoice->status);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'payer_id' => $this->customer->id,
            'amount' => 400000,
        ]);
    }

    public function test_cross_company_wallet_isolation(): void
    {
        $this->service->depositWalletCredit($this->customer, 500000, 'Company A deposit');

        $companyB = Company::factory()->create(['fiscal_year' => 1405]);
        $groupB = CustomerGroup::factory()->withSubject()->create([
            'name' => 'Group B',
            'company_id' => $companyB->id,
        ]);
        $customerB = Customer::factory()->withGroup($groupB)->withSubject()->create([
            'company_id' => $companyB->id,
            'name' => 'مشتری شرکت ب',
        ]);

        $walletB = $this->service->getOrCreateWallet($customerB);
        $this->assertSame(0, $walletB->balance);

        $response = $this->actingAs($this->user)->get(route('loyalty.index'));
        $response->assertOk();
        $response->assertSee('مشتری وفادار');
        $response->assertDontSee('مشتری شرکت ب');
    }
}
