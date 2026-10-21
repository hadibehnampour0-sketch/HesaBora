<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PosTerminal;
use App\Models\PosTransaction;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\User;
use App\Services\PaymentService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class PosTerminalIntegrationTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9000;

    protected ?int $bankSubjectId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'شرکت تست دخل', 'fiscal_year' => 1405])->id;

        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->grant(
            'access-super-admin-panel',
            'pc-pos.index',
            'pc-pos.store',
            'pc-pos.update',
            'pc-pos.destroy',
            'pc-pos.ping',
            'pc-pos.pay-invoice',
            'invoices.index',
            'invoices.show',
            'invoices.create',
            'invoices.store',
            'invoices.payments.store'
        );

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        ProductGroup::factory()->withSubjects()->create(['name' => 'عمومی', 'vat' => 0, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'عمومی', 'description' => 'گروه مشتریان', 'company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);
    }

    private function grant(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
    }

    private function bankAccountSubject(): Subject
    {
        $bank = new Bank(['name' => 'بانک ملت نمونه']);
        $bank->company_id = $this->companyId;
        $bank->save();

        $account = BankAccount::factory()->withSubject()->create([
            'company_id' => $this->companyId,
            'bank_id' => $bank->id,
        ]);

        return Subject::withoutGlobalScopes()->findOrFail($account->subject_id);
    }

    private function approvedSell(float $unit, int $qty): Invoice
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, $qty, $unit * 0.5)], true, ++$this->nextInvoiceNumber);
        $sell = $this->sell([$this->productItem($product, $qty, $unit)], true, ++$this->nextInvoiceNumber)['invoice'];

        return $sell;
    }

    public function test_pos_terminal_can_be_created_and_listed(): void
    {
        $subject = $this->bankAccountSubject();

        $response = $this->post(route('pc-pos.store'), [
            'name' => 'کارتخوان صندوق مرکزی',
            'psp' => 'behpardakht',
            'connection_type' => 'lan',
            'ip_address' => '192.168.1.180',
            'port' => 8888,
            'terminal_id' => '98765432',
            'bank_account_subject_id' => $subject->id,
            'is_default' => '1',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('pc-pos.index'));

        $this->assertDatabaseHas('pos_terminals', [
            'company_id' => $this->companyId,
            'name' => 'کارتخوان صندوق مرکزی',
            'psp' => 'behpardakht',
            'ip_address' => '192.168.1.180',
            'port' => 8888,
            'is_default' => 1,
            'is_active' => 1,
        ]);

        $this->get(route('pc-pos.index'))
            ->assertOk()
            ->assertSee('کارتخوان صندوق مرکزی')
            ->assertSee('به‌پرداخت ملت');
    }

    public function test_pos_terminal_ping_connection(): void
    {
        $terminal = PosTerminal::create([
            'company_id' => $this->companyId,
            'name' => 'پوز شبیه‌ساز',
            'psp' => 'simulator',
            'connection_type' => 'lan',
            'is_active' => true,
        ]);

        $response = $this->postJson(route('pc-pos.ping', $terminal));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_online', true)
            ->assertJsonPath('status', 'online');

        $this->assertSame('online', $terminal->fresh()->last_status);
        $this->assertNotNull($terminal->fresh()->last_ping_at);
    }

    public function test_invoice_payment_via_simulator_pos_settles_invoice_and_creates_payment(): void
    {
        $subject = $this->bankAccountSubject();

        $terminal = PosTerminal::create([
            'company_id' => $this->companyId,
            'name' => 'پوز فروشگاهی تستی',
            'psp' => 'simulator',
            'connection_type' => 'lan',
            'bank_account_subject_id' => $subject->id,
            'is_active' => true,
            'is_default' => true,
        ]);

        $invoice = $this->approvedSell(100000, 1);
        $paymentService = app(PaymentService::class);

        $this->assertEqualsWithDelta(100000, $paymentService->remainingAmount($invoice), 0.01);

        // Send payment to POS
        $response = $this->postJson(route('pc-pos.pay-invoice', $invoice), [
            'pos_terminal_id' => $terminal->id,
            'amount' => 100000,
            'simulate_scenario' => 'success',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('invoice_id', $invoice->id);

        // Verify PosTransaction record
        $tx = PosTransaction::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($tx);
        $this->assertSame('success', $tx->status);
        $this->assertSame('00', $tx->response_code);
        $this->assertNotNull($tx->rrn);
        $this->assertNotNull($tx->card_number_masked);
        $this->assertNotNull($tx->payment_id);

        // Verify official Payment record was created by PaymentService
        $payment = Payment::find($tx->payment_id);
        $this->assertNotNull($payment);
        $this->assertEquals(100000, (float) $payment->amount);
        $this->assertEquals($subject->id, $payment->settlement_subject_id);
        $this->assertNotNull($payment->document_id);

        // Verify Invoice is fully settled
        $this->assertEqualsWithDelta(0, $paymentService->remainingAmount($invoice->fresh()), 0.01);
    }

    public function test_invoice_payment_handles_cancellation_gracefully(): void
    {
        $subject = $this->bankAccountSubject();

        $terminal = PosTerminal::create([
            'company_id' => $this->companyId,
            'name' => 'پوز شبیه‌ساز',
            'psp' => 'simulator',
            'connection_type' => 'lan',
            'bank_account_subject_id' => $subject->id,
            'is_active' => true,
        ]);

        $invoice = $this->approvedSell(50000, 1);
        $paymentService = app(PaymentService::class);

        $response = $this->postJson(route('pc-pos.pay-invoice', $invoice), [
            'pos_terminal_id' => $terminal->id,
            'amount' => 50000,
            'simulate_scenario' => 'cancel',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 'cancelled');

        $tx = PosTransaction::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($tx);
        $this->assertSame('cancelled', $tx->status);
        $this->assertNull($tx->payment_id);

        // Invoice remains unpaid
        $this->assertEqualsWithDelta(50000, $paymentService->remainingAmount($invoice->fresh()), 0.01);
        $this->assertEquals(0, Payment::where('invoice_id', $invoice->id)->count());
    }

    public function test_terminals_are_isolated_by_company(): void
    {
        PosTerminal::create([
            'company_id' => $this->companyId,
            'name' => 'کارتخوان شرکت یک',
            'psp' => 'simulator',
            'is_active' => true,
        ]);

        $otherCompany = Company::factory()->create(['name' => 'شرکت دوم']);

        $otherTerminals = PosTerminal::where('company_id', $otherCompany->id)->get();
        $this->assertCount(0, $otherTerminals);
    }
}
