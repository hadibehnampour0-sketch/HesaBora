<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\SmsConfiguration;
use App\Models\SmsLog;
use App\Models\Subject;
use App\Models\User;
use App\Services\Payment\PaymentLinkService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class PaymentLinkTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;
    protected Company $company;
    protected Customer $customer;
    protected Subject $bankSubject;
    protected Subject $customerSubject;
    protected BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::firstOrCreate(['id' => 1], [
            'name' => 'شرکت آزمایشی دخل',
            'fiscal_year' => 1405,
        ]);
        \Illuminate\Support\Facades\Cache::forever('active_company_id', $this->company->id);
        \Illuminate\Support\Facades\Cookie::queue('active-company-id', (string) $this->company->id);
        $_COOKIE['active-company-id'] = (string) $this->company->id;

        $this->importSubjects($this->company->id);
        $this->importConfigs($this->company->id);

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->company->users()->syncWithoutDetaching($this->user);

        $bankRoot = Subject::withoutGlobalScopes()->find((int) config('amir.bank'));
        $this->bankSubject = Subject::factory()->withParent($bankRoot)->create([
            'name' => 'بانک سامان تجارت',
            'company_id' => $this->company->id,
        ]);

        $bank = \App\Models\Bank::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'سامان',
        ]);
        $this->bankAccount = BankAccount::create([
            'company_id' => $this->company->id,
            'name' => 'حساب جاری آنلاین سامان',
            'number' => '8888-1111-2222',
            'type' => \App\Enums\BankAccountType::CURRENT,
            'bank_id' => $bank->id,
            'subject_id' => $this->bankSubject->id,
        ]);

        $customerGroup = \App\Models\CustomerGroup::factory()->withSubject()->create([
            'name' => 'General',
            'company_id' => $this->company->id,
        ]);

        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create([
            'company_id' => $this->company->id,
            'name' => 'سهراب سپهری',
            'mobile' => '09123456789',
        ]);
    }

    public function test_generate_payment_link_for_invoice(): void
    {
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED,
            'number' => 2001,
            'amount' => 15000000,
            'final_amount' => 15000000,
            'vat' => 0,
            'date' => '2026-10-20',
        ]);

        $service = app(PaymentLinkService::class);
        $link = $service->generateInvoicePaymentLink($invoice, null, 'sandbox', $this->user);

        $this->assertDatabaseHas('payment_links', [
            'id' => $link->id,
            'company_id' => $this->company->id,
            'invoice_id' => $invoice->id,
            'amount' => 15000000,
            'status' => 'pending',
            'gateway' => 'sandbox',
        ]);

        $this->assertNotNull($link->token);
        $this->assertStringContainsString($link->token, $link->public_url);
    }

    public function test_public_portal_invoice_view(): void
    {
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED,
            'number' => 2002,
            'amount' => 8500000,
            'final_amount' => 8500000,
            'vat' => 0,
            'date' => '2026-10-20',
        ]);

        $link = PaymentLink::create([
            'company_id' => $this->company->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'amount' => 8500000,
            'gateway' => 'sandbox',
        ]);

        // Access unauthenticated via public portal
        auth()->logout();

        $response = $this->get(route('portal.invoice.show', ['token' => $link->token]));
        $response->assertOk();
        $response->assertSee('2002');
        $response->assertSee('سهراب سپهری');
        $response->assertSee(number_format(8500000));
        $response->assertSee('پرداخت آنلاین');
    }

    public function test_initiate_payment_redirects_to_sandbox_gateway(): void
    {
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED,
            'number' => 2003,
            'amount' => 5000000,
            'final_amount' => 5000000,
            'vat' => 0,
            'date' => '2026-10-20',
        ]);

        $link = PaymentLink::create([
            'company_id' => $this->company->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'amount' => 5000000,
            'gateway' => 'sandbox',
        ]);

        auth()->logout();

        $response = $this->post(route('portal.invoice.pay', ['token' => $link->token]));
        $response->assertRedirect();

        $link->refresh();
        $this->assertNotNull($link->gateway_token);
        $this->assertStringStartsWith('SBX-', $link->gateway_token);
    }

    public function test_payment_callback_settles_invoice_and_creates_balanced_document(): void
    {
        // Configure SMS auto receipt
        SmsConfiguration::create([
            'company_id' => $this->company->id,
            'provider' => 'log',
            'is_active' => true,
            'auto_payment_received' => true,
        ]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED,
            'number' => 2004,
            'amount' => 6000000,
            'final_amount' => 6000000,
            'vat' => 0,
            'date' => '2026-10-20',
        ]);

        $link = PaymentLink::create([
            'company_id' => $this->company->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'amount' => 6000000,
            'gateway' => 'sandbox',
            'created_by' => $this->user->id,
        ]);

        auth()->logout();

        $response = $this->get(route('portal.invoice.callback', [
            'token' => $link->token,
            'status' => 'OK',
            'authority' => 'SBX-MOCK1234',
        ]));

        $response->assertOk();
        $response->assertSee('پرداخت با موفقیت انجام شد');

        $link->refresh();
        $this->assertEquals(PaymentLink::STATUS_PAID, $link->status);
        $this->assertNotNull($link->gateway_reference);
        $this->assertNotNull($link->paid_at);
        $this->assertNotNull($link->payment_id);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::PAID, $invoice->status);

        // Verify Payment record and Document
        $payment = Payment::withoutGlobalScopes()->find($link->payment_id);
        $this->assertNotNull($payment);
        $this->assertEquals(6000000, (float) $payment->amount);
        $this->assertNotNull($payment->document_id);

        $document = Document::withoutGlobalScopes()->with('transactions')->find($payment->document_id);
        $this->assertNotNull($document);
        $this->assertNotNull($document->approved_at);

        // Accounting balance verification: sum of transactions must be 0
        $sum = $document->transactions->sum('value');
        $this->assertEquals(0, $sum, 'The document generated by online payment must be perfectly balanced!');

        // Verify SMS receipt was created
        $this->assertDatabaseHas('sms_logs', [
            'company_id' => $this->company->id,
            'event_type' => 'payment_received',
        ]);
    }

    public function test_expired_payment_link_cannot_be_paid(): void
    {
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED,
            'number' => 2005,
            'amount' => 4000000,
            'final_amount' => 4000000,
            'vat' => 0,
            'date' => '2026-10-20',
        ]);

        $link = PaymentLink::create([
            'company_id' => $this->company->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'amount' => 4000000,
            'gateway' => 'sandbox',
            'expires_at' => Carbon::now()->subDays(1),
        ]);

        auth()->logout();

        $response = $this->post(route('portal.invoice.pay', ['token' => $link->token]));
        $response->assertRedirect(route('portal.invoice.show', ['token' => $link->token]));
        $response->assertSessionHas('error');

        $this->assertFalse($link->fresh()->isPaid());
    }
}
