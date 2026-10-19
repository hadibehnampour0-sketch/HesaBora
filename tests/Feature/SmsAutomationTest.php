<?php

namespace Tests\Feature;

use App\Console\Commands\SendChequeDueRemindersCommand;
use App\Enums\ChequeType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Events\InvoiceStatusChanged;
use App\Jobs\SendSmsJob;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SmsConfiguration;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Services\Sms\SmsService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class SmsAutomationTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;
    protected Customer $customer;
    protected int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;
        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'General', 'company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create([
            'company_id' => $this->companyId,
            'mobile' => '09121234567',
            'name' => 'Ali Rezai',
        ]);

        $this->user = User::factory()->create();
        Company::find($this->companyId)->users()->syncWithoutDetaching($this->user);

        // Permissions for SMS routes
        Permission::firstOrCreate(['name' => 'sms.config.update']);
        Permission::firstOrCreate(['name' => 'sms.template.update']);
        $this->user->givePermissionTo('sms.config.update', 'sms.template.update');
    }

    public function test_phone_number_normalization(): void
    {
        $this->assertEquals('09121234567', SmsService::normalizePhone('09121234567'));
        $this->assertEquals('09121234567', SmsService::normalizePhone('+989121234567'));
        $this->assertEquals('09121234567', SmsService::normalizePhone('00989121234567'));
        $this->assertEquals('09121234567', SmsService::normalizePhone('۹۱۲۱۲۳۴۵۶۷')); // 10 digits without leading zero
        $this->assertEquals('09121234567', SmsService::normalizePhone('۰۹۱۲۱۲۳۴۵۶۷')); // Persian digits
        $this->assertNull(SmsService::normalizePhone('12345'));
        $this->assertNull(SmsService::normalizePhone(''));
    }

    public function test_sms_configuration_and_template_save(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('sms.config.update'), [
            'provider' => 'kavenegar',
            'api_key' => 'secret_test_key_123',
            'sender_number' => '10008000',
            'is_active' => '1',
            'auto_invoice_approved' => '1',
            'auto_payment_received' => '1',
            'auto_cheque_due_reminder' => '1',
        ]);

        $response->assertRedirect();

        $config = SmsConfiguration::where('company_id', $this->companyId)->first();
        $this->assertNotNull($config);
        $this->assertEquals('kavenegar', $config->provider);
        $this->assertEquals('secret_test_key_123', $config->api_key);
        $this->assertTrue($config->is_active);

        // Update template
        $templateResponse = $this->post(route('sms.template.update', 'invoice_approved'), [
            'pattern_code' => 'p123',
            'template_text' => 'Hello {customer_name}, invoice {invoice_number} is {amount} Rials.',
            'is_active' => '1',
        ]);

        $templateResponse->assertRedirect();
        $template = SmsTemplate::where('company_id', $this->companyId)->where('event_type', 'invoice_approved')->first();
        $this->assertNotNull($template);
        $this->assertEquals('p123', $template->pattern_code);
    }

    public function test_sms_dispatch_creates_log_and_processes_via_job(): void
    {
        Queue::fake([SendSmsJob::class]);

        SmsConfiguration::create([
            'company_id' => $this->companyId,
            'provider' => 'log',
            'is_active' => true,
        ]);

        $smsService = app(SmsService::class);
        $log = $smsService->dispatchAsync(
            recipient: '09121234567',
            message: 'Direct test message',
            eventType: 'test',
            companyId: $this->companyId
        );

        $this->assertDatabaseHas('sms_logs', [
            'id' => $log->id,
            'recipient' => '09121234567',
            'status' => 'pending',
        ]);

        Queue::assertPushed(SendSmsJob::class);

        // Process job
        $job = new SendSmsJob($log->id);
        $job->handle($smsService);

        $log->refresh();
        $this->assertEquals('sent', $log->status);
        $this->assertNotNull($log->sent_at);
    }

    public function test_invoice_approval_domain_event_triggers_sms(): void
    {
        SmsConfiguration::create([
            'company_id' => $this->companyId,
            'provider' => 'log',
            'is_active' => true,
            'auto_invoice_approved' => true,
        ]);

        SmsTemplate::create([
            'company_id' => $this->companyId,
            'event_type' => 'invoice_approved',
            'template_text' => 'فاکتور {invoice_number} برای {customer_name} صادر شد: {amount} ریال',
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'company_id' => $this->companyId,
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED,
            'number' => 101,
            'amount' => 5000000,
            'final_amount' => 5000000,
            'vat' => 0,
            'date' => '2026-10-20',
        ]);

        // Fire status changed event
        event(new InvoiceStatusChanged($invoice, InvoiceStatus::PENDING, InvoiceStatus::APPROVED));

        $this->assertDatabaseHas('sms_logs', [
            'company_id' => $this->companyId,
            'recipient' => '09121234567',
            'event_type' => 'invoice_approved',
        ]);

        $log = SmsLog::where('event_type', 'invoice_approved')->first();
        $this->assertStringContainsString('101', $log->message);
        $this->assertStringContainsString('Ali Rezai', $log->message);
    }

    public function test_payment_creation_triggers_sms_receipt(): void
    {
        SmsConfiguration::create([
            'company_id' => $this->companyId,
            'provider' => 'log',
            'is_active' => true,
            'auto_payment_received' => true,
        ]);

        Payment::create([
            'payer_id' => $this->customer->id,
            'amount' => 2500000,
            'date' => '2026-10-20',
            'reference_number' => 'REF-9988',
        ]);

        $this->assertDatabaseHas('sms_logs', [
            'company_id' => $this->companyId,
            'recipient' => '09121234567',
            'event_type' => 'payment_received',
        ]);

        $log = SmsLog::where('event_type', 'payment_received')->first();
        $this->assertStringContainsString('2,500,000', $log->message);
    }

    public function test_cheque_due_date_reminder_command(): void
    {
        SmsConfiguration::create([
            'company_id' => $this->companyId,
            'provider' => 'log',
            'is_active' => true,
            'auto_cheque_due_reminder' => true,
        ]);

        Cheque::create([
            'company_id' => $this->companyId,
            'customer_id' => $this->customer->id,
            'direction' => ChequeType::RECEIVABLE,
            'purpose' => ChequeType::SETTLEMENT,
            'status' => ChequeType::REGISTERED,
            'amount' => 12000000,
            'write_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(2)->toDateString(),
            'cheque_number' => 'CHK-7744',
            'sayad_number' => '1234567890123456',
        ]);

        $this->artisan('dakhl:send-cheque-reminders', ['--days' => 3])
            ->assertSuccessful();

        $this->assertDatabaseHas('sms_logs', [
            'company_id' => $this->companyId,
            'recipient' => '09121234567',
            'event_type' => 'cheque_due',
        ]);

        $log = SmsLog::where('event_type', 'cheque_due')->first();
        $this->assertStringContainsString('CHK-7744', $log->message);
        $this->assertStringContainsString('12,000,000', $log->message);
    }
}
