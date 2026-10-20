<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\Invoice;
use App\Models\TelephonyCallLog;
use App\Models\User;
use App\Services\Telephony\CallerIdService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CallerIdTelephonyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected CallerIdService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::firstOrCreate(['id' => 1], [
            'name' => 'Dakhl Telephony Test Corp',
            'fiscal_year' => 1405,
        ]);

        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);

        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'telephony.*']));

        Cache::forever('active_company_id', $this->company->id);
        Cookie::queue('active-company-id', (string) $this->company->id);
        $_COOKIE['active-company-id'] = (string) $this->company->id;

        $this->service = app(CallerIdService::class);
    }

    public function test_phone_number_normalization(): void
    {
        $this->assertSame('09121234567', $this->service->normalizePhoneNumber('+989121234567'));
        $this->assertSame('09121234567', $this->service->normalizePhoneNumber('00989121234567'));
        $this->assertSame('09121234567', $this->service->normalizePhoneNumber('09121234567'));
        $this->assertSame('02188889999', $this->service->normalizePhoneNumber('+982188889999'));
        $this->assertSame('02188889999', $this->service->normalizePhoneNumber('02188889999'));
    }

    public function test_resolve_customer_profile_with_loyalty_and_open_invoices(): void
    {
        $customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Ali Mohammadi',
            'mobile' => '09123456789',
            'phone' => '02122223333',
            'balance' => 4500000,
        ]);

        CustomerWallet::create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'tier' => 'GOLD',
            'points' => 350,
            'store_credit' => 200000,
        ]);

        Invoice::create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'number' => 1001,
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED,
            'amount' => 1200000,
            'vat' => 0,
            'final_amount' => 1200000,
            'date' => '2026-10-20',
        ]);

        // Resolve by mobile
        $profile = $this->service->resolveCustomerProfile('09123456789', $this->company->id);
        $this->assertTrue($profile['found']);
        $this->assertSame($customer->id, $profile['customer']['id']);
        $this->assertSame('Ali Mohammadi', $profile['customer']['name']);
        $this->assertSame(4500000, $profile['balance']);
        $this->assertSame('GOLD', $profile['loyalty']['tier']);
        $this->assertSame(350, $profile['loyalty']['points']);
        $this->assertSame(1, $profile['open_invoices_count']);
        $this->assertSame(1200000, $profile['open_invoices_amount']);

        // Resolve by international format
        $profileIntl = $this->service->resolveCustomerProfile('+989123456789', $this->company->id);
        $this->assertTrue($profileIntl['found']);
        $this->assertSame($customer->id, $profileIntl['customer']['id']);
    }

    public function test_resolve_unknown_number(): void
    {
        $profile = $this->service->resolveCustomerProfile('09999999999', $this->company->id);

        $this->assertFalse($profile['found']);
        $this->assertNull($profile['customer']);
        $this->assertSame(0, $profile['balance']);
        $this->assertSame('BRONZE', $profile['loyalty']['tier']);
        $this->assertSame(0, $profile['open_invoices_count']);
    }

    public function test_webhook_ingests_incoming_call(): void
    {
        $customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Sara Rad',
            'mobile' => '09351112233',
            'balance' => 0,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('telephony.webhook'), [
            'caller_number' => '+989351112233',
            'channel' => 'voip',
            'extension' => '102',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.caller_number', '09351112233')
            ->assertJsonPath('data.call_status', 'RINGING')
            ->assertJsonPath('data.profile.found', true)
            ->assertJsonPath('data.profile.customer.name', 'Sara Rad');

        $this->assertDatabaseHas('telephony_call_logs', [
            'company_id' => $this->company->id,
            'caller_number' => '09351112233',
            'customer_id' => $customer->id,
            'channel' => 'voip',
            'extension' => '102',
            'call_status' => 'RINGING',
        ]);
    }

    public function test_lookup_endpoint_returns_json(): void
    {
        Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Kaveh Tehrani',
            'mobile' => '09120001122',
            'balance' => 1500000,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('telephony.lookup', ['number' => '09120001122']));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.found', true)
            ->assertJsonPath('data.customer.name', 'Kaveh Tehrani');
    }

    public function test_call_status_transition(): void
    {
        $log = TelephonyCallLog::create([
            'company_id' => $this->company->id,
            'caller_number' => '09120000000',
            'channel' => 'voip',
            'call_status' => 'RINGING',
        ]);

        $response = $this->actingAs($this->user)->postJson(route('telephony.logs.status', $log->id), [
            'status' => 'ANSWERED',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.call_status', 'ANSWERED');

        $this->assertSame('ANSWERED', $log->fresh()->call_status);
    }

    public function test_cross_company_isolation(): void
    {
        $company2 = Company::create([
            'name' => 'Another Company',
            'fiscal_year' => 1405,
        ]);

        Customer::create([
            'company_id' => $company2->id,
            'name' => 'Secret Customer in Company 2',
            'mobile' => '09128887766',
            'balance' => 999999,
        ]);

        // Attempt resolving from Company 1
        $profile = $this->service->resolveCustomerProfile('09128887766', $this->company->id);

        $this->assertFalse($profile['found']);
        $this->assertNull($profile['customer']);
    }
}
