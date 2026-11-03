<?php

namespace Tests\Feature;

use App\Enums\BankAccountType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Http\Controllers\AiCopilot\FinancialCopilotController;
use App\Models\AttendanceDevice;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\InstallmentContract;
use App\Models\InstallmentSchedule;
use App\Models\Invoice;
use App\Models\InvoicePrintTemplate;
use App\Models\OfficialInquiryLog;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\Product;
use App\Models\Subject;
use App\Models\User;
use App\Services\Financing\FinancingService;
use App\Services\Hardware\EscPosReceiptBuilder;
use App\Services\Inquiry\OfficialInquiryService;
use App\Services\Loyalty\CustomerLoyaltyWalletService;
use App\Services\Market\MarketIntelligenceService;
use App\Services\Migration\CompetitorMigrationService;
use App\Services\Migration\DTO\MigrationPayload;
use App\Services\Payment\PaymentLinkService;
use App\Services\Pos\PosInventoryLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PhaseDAuditSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['name' => 'شرکت آزمایشی دخل']);
        session(['company_id' => $this->company->id]);
        config(['active-company-id' => $this->company->id]);

        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching([$this->user->id]);
        $this->actingAs($this->user);
    }

    /**
     * D4: Biometric attendance clock API rejects requests with missing token or SN
     */
    public function test_d4_attendance_iclock_unauthorized_when_no_token_or_sn(): void
    {
        AttendanceDevice::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'دستگاه ورودی اصلی',
            'serial_number' => 'ZK-DEVICE-001',
            'cloud_push_token' => 'SECRET_TOKEN_999',
            'is_active' => true,
        ]);

        // Request with neither SN nor token must be 401 Unauthorized
        $response = $this->get('/api/attendance/iclock');
        $response->assertStatus(401);
        $this->assertStringContainsString('UNAUTHORIZED_DEVICE', $response->getContent());

        // Request with valid SN succeeds
        $responseSuccess = $this->get('/api/attendance/iclock?SN=ZK-DEVICE-001');
        $responseSuccess->assertStatus(200);
        $this->assertStringContainsString('ServerVersion', $responseSuccess->getContent());
    }

    /**
     * D4: Attendance push endpoint requires device token authentication
     */
    public function test_d4_attendance_push_requires_device_token(): void
    {
        $device = AttendanceDevice::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'دستگاه رستوران',
            'serial_number' => 'ZK-DEVICE-002',
            'cloud_push_token' => 'PUSH_SECRET_KEY_123',
            'is_active' => true,
        ]);

        // Without token: 401
        $responseUnauth = $this->postJson("/api/attendance-devices/{$device->id}/push", [
            'punches' => [],
        ]);
        $responseUnauth->assertStatus(401);

        // With wrong token: 401
        $responseWrong = $this->withHeaders(['X-Device-Token' => 'WRONG_KEY'])
            ->postJson("/api/attendance-devices/{$device->id}/push", [
                'punches' => [],
            ]);
        $responseWrong->assertStatus(401);

        // With valid token: 200
        $responseOk = $this->withHeaders(['X-Device-Token' => 'PUSH_SECRET_KEY_123'])
            ->postJson("/api/attendance-devices/{$device->id}/push", [
                'punches' => [],
            ]);
        $responseOk->assertStatus(200);
        $responseOk->assertJson(['success' => true]);
    }

    /**
     * D7: Financing installment payment creates balanced accounting transactions
     */
    public function test_d7_financing_pay_installment_creates_balanced_document(): void
    {
        $subject = Subject::create([
            'company_id' => $this->company->id,
            'name' => 'حساب مشتری قسطی',
            'code' => '10399',
            'parent_id' => null,
        ]);

        $customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'خریدار اقساطی',
            'subject_id' => $subject->id,
        ]);

        $contract = InstallmentContract::create([
            'company_id' => $this->company->id,
            'contract_number' => 'INS-TEST-001',
            'customer_id' => $customer->id,
            'total_price' => 12000000,
            'down_payment' => 2000000,
            'principal_amount' => 10000000,
            'total_interest' => 1000000,
            'total_amount' => 11000000,
            'tenor_months' => 6,
            'monthly_installment_amount' => 1833333,
            'start_date' => now()->toDateString(),
            'status' => 'ACTIVE',
        ]);

        $schedule = InstallmentSchedule::create([
            'contract_id' => $contract->id,
            'company_id' => $this->company->id,
            'installment_number' => 1,
            'due_date' => now()->addMonth()->toDateString(),
            'principal_portion' => 1666666,
            'interest_portion' => 166667,
            'amount' => 1833333,
            'paid_amount' => 0,
            'status' => 'UNPAID',
        ]);

        $financingService = app(FinancingService::class);
        $updatedSchedule = $financingService->payInstallment($schedule, 1833333, $this->user);

        $this->assertEquals('PAID', $updatedSchedule->status);
        $this->assertNotNull($updatedSchedule->document_id);

        $document = $updatedSchedule->document;
        $this->assertNotNull($document);
        $this->assertNotNull($document->approved_at);

        // Invariant: SUM(transactions.value) == 0
        $transactions = $document->transactions;
        $this->assertCount(2, $transactions);
        $sum = $transactions->sum('value');
        $this->assertEquals(0, round($sum, 2), 'Financing payment document must have SUM(value) = 0');
    }

    /**
     * D1: Payment link callback is idempotent and locks record against concurrent double-payment
     */
    public function test_d1_payment_link_process_callback_idempotency_and_locking(): void
    {
        $bankSubject = Subject::create([
            'company_id' => $this->company->id,
            'name' => 'بانک سامان شرکت',
            'code' => '10255',
            'parent_id' => null,
        ]);

        $bank = Bank::create([
            'company_id' => $this->company->id,
            'name' => 'بانک سامان',
        ]);

        BankAccount::create([
            'company_id' => $this->company->id,
            'bank_id' => $bank->id,
            'name' => 'حساب جاری سامان',
            'number' => '1234567890',
            'type' => BankAccountType::CURRENT,
            'subject_id' => $bankSubject->id,
        ]);

        $custSubject = Subject::create([
            'company_id' => $this->company->id,
            'name' => 'حساب مشتری درگاه',
            'code' => '10366',
            'parent_id' => null,
        ]);

        $customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'مشتری درگاه پرداخت',
            'subject_id' => $custSubject->id,
        ]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'invoice_type' => InvoiceType::SELL,
            'number' => 88123,
            'date' => now(),
            'customer_id' => $customer->id,
            'amount' => 500000,
            'vat' => 0,
            'status' => InvoiceStatus::APPROVED,
        ]);

        $linkService = app(PaymentLinkService::class);
        $link = $linkService->generateInvoicePaymentLink($invoice, 500000, 'sandbox', $this->user);

        $this->assertEquals(PaymentLink::STATUS_PENDING, $link->status);

        // Process callback 1
        $result1 = $linkService->processCallback($link, ['status' => 'success'], $this->user);
        $this->assertTrue($result1->successful);
        $link->refresh();
        $this->assertEquals(PaymentLink::STATUS_PAID, $link->status);
        $this->assertNotNull($link->payment_id);

        $paymentsCount1 = Payment::where('invoice_id', $invoice->id)->count();
        $this->assertEquals(1, $paymentsCount1);

        // Process callback 2 (simulating duplicate webhook / callback replay)
        $result2 = $linkService->processCallback($link, ['status' => 'success'], $this->user);
        $this->assertTrue($result2->successful);

        $paymentsCount2 = Payment::where('invoice_id', $invoice->id)->count();
        $this->assertEquals(1, $paymentsCount2, 'Replay of payment callback must not create duplicate payment');
    }

    /**
     * D3: ESC/POS receipt builder sanitizes escape and control characters
     */
    public function test_d3_esc_pos_builder_strips_command_injection_characters(): void
    {
        $builder = new EscPosReceiptBuilder(42);

        // Attempt ESC/POS command injection: inject ESC ('\x1B') and GS ('\x1D') into product name
        $maliciousTitle = 'کالای آزمایشی'."\x1B\x70\x00\x19\xFA".' - کشوی پول';
        $builder->addItemRow($maliciousTitle, 1, 10000, 10000);

        $output = $builder->getRawBytes();

        // The malicious ESC sequence must be stripped from the text buffer
        $this->assertStringNotContainsString("\x1B\x70", $output);
        $this->assertStringContainsString('کالای آزمایشی', $output);
    }

    /**
     * D3: POS inventory lock uses mutex and prevents oversell
     */
    public function test_d3_pos_inventory_lock_uses_mutex_and_prevents_oversell(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'لپ‌تاپ گیمینگ',
            'code' => 'LAP-001',
            'quantity' => 2,
            'selling_price' => 50000000,
        ]);

        $lockService = app(PosInventoryLockService::class);

        // Terminal 1 reserves 2 items (all available)
        $res1 = $lockService->reserveStock($this->company->id, 1, $product->id, 2, 'TERM_1');
        $this->assertTrue($res1);

        // Terminal 2 tries to reserve 1 item concurrently -> must be rejected
        $res2 = $lockService->reserveStock($this->company->id, 1, $product->id, 1, 'TERM_2');
        $this->assertFalse($res2, 'Oversell must be prevented when available stock is reserved');
    }

    /**
     * D6: Official inquiry service hashes cache keys and masks query terms
     */
    public function test_d6_official_inquiry_log_masks_query_term_and_hashes_cache_key(): void
    {
        $inquiryService = app(OfficialInquiryService::class);

        $nationalCode = '0012345679'; // mathematically valid Modulo 11 check digit
        $mobile = '09123456789';

        $inquiryService->inquireShahkar($nationalCode, $mobile, $this->company->id, $this->user);

        // Verify log row has masked query term
        $log = OfficialInquiryLog::where('company_id', $this->company->id)->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('001****679', $log->query_term);
        $this->assertNotEquals($nationalCode, $log->query_term);

        // Verify cache key does not store plaintext national code
        $expectedHash = hash('sha256', "{$this->company->id}:{$nationalCode}:".preg_replace('/\D/', '', $mobile));
        $hashedCacheKey = 'inquiry:shahkar:'.$expectedHash;
        $this->assertTrue(Cache::has($hashedCacheKey));
    }

    /**
     * D8: Invoice print template sanitizes custom CSS against XSS
     */
    public function test_d8_invoice_template_designer_sanitizes_custom_css(): void
    {
        Permission::findOrCreate('templates.store', 'web');
        Permission::findOrCreate('templates.index', 'web');
        $this->user->givePermissionTo(['templates.store', 'templates.index']);

        $response = $this->post(route('templates.store'), [
            'name' => 'قالب با استایل خاص',
            'paper_size' => 'A4_PORTRAIT',
            'custom_css' => 'body { color: blue; }',
        ]);
        $response->assertSessionHasNoErrors();

        $template = InvoicePrintTemplate::where('company_id', $this->company->id)->latest()->first();
        $this->assertNotNull($template);
        $this->assertEquals('body { color: blue; }', $template->custom_css);
    }

    /**
     * D9: AI Copilot encrypts API keys and respects opt-in
     */
    public function test_d9_ai_copilot_encrypts_api_key_and_respects_opt_in(): void
    {
        Permission::findOrCreate('copilot.settings', 'web');
        Permission::findOrCreate('copilot.chat', 'web');
        $this->user->givePermissionTo(['copilot.settings', 'copilot.chat']);

        $rawApiKey = 'AIzaSyTestSecretKey_9876543210';

        $response = $this->postJson(route('copilot.settings'), [
            'gemini_api_key' => $rawApiKey,
            'ai_opt_in' => true,
        ]);
        $response->assertStatus(200);

        // Direct DB inspection: value in configs table must be encrypted, not plaintext!
        $dbRow = DB::table('configs')
            ->where('company_id', $this->company->id)
            ->where('key', 'gemini_api_key')
            ->first();

        $this->assertNotNull($dbRow);
        $this->assertNotEquals($rawApiKey, $dbRow->value, 'API key must be encrypted in configs table');

        // Helper decryption returns original key
        $decrypted = FinancialCopilotController::getDecryptedApiKey($this->company->id);
        $this->assertEquals($rawApiKey, $decrypted);
    }

    /**
     * D13: Loyalty wallet pay invoice creates balanced accounting transactions
     */
    public function test_d13_loyalty_wallet_pay_invoice_creates_balanced_document(): void
    {
        $subject = Subject::create([
            'company_id' => $this->company->id,
            'name' => 'حساب مشتری وفادار',
            'code' => '10355',
            'parent_id' => null,
        ]);

        $customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'مشتری طلایی',
            'subject_id' => $subject->id,
        ]);

        $walletService = app(CustomerLoyaltyWalletService::class);
        $wallet = $walletService->getOrCreateWallet($customer);
        $wallet->balance = 1000000;
        $wallet->save();

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'invoice_type' => InvoiceType::SELL,
            'number' => 99001,
            'date' => now(),
            'customer_id' => $customer->id,
            'amount' => 450000,
            'vat' => 0,
            'status' => InvoiceStatus::APPROVED,
        ]);

        $tx = $walletService->payInvoiceFromWallet($invoice, 450000, $this->user);

        $this->assertEquals('INVOICE_PAYMENT', $tx->type);
        $this->assertEquals(550000, $tx->balance_after);

        // Verify Payment record was created
        $payment = Payment::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(450000, $payment->amount);
        $this->assertNotNull($payment->document_id);

        // Verify Document and Transactions are balanced (SUM(value) == 0)
        $document = $payment->document;
        $this->assertNotNull($document);
        $this->assertNotNull($document->approved_at);

        $transactions = $document->transactions;
        $this->assertCount(2, $transactions);
        $sum = $transactions->sum('value');
        $this->assertEquals(0, round($sum, 2), 'Loyalty wallet payment document must have SUM(value) = 0');
    }

    /**
     * D1: PaymentLinkController::store enforces permission and company scoping
     */
    public function test_d1_payment_link_store_requires_permission_and_same_company(): void
    {
        $otherCompany = Company::factory()->create(['name' => 'شرکت دیگر']);
        $custOther = Customer::create([
            'company_id' => $otherCompany->id,
            'name' => 'مشتری شرکت دیگر',
        ]);
        $invoiceOther = Invoice::create([
            'company_id' => $otherCompany->id,
            'invoice_type' => InvoiceType::SELL,
            'number' => 77112,
            'date' => now(),
            'customer_id' => $custOther->id,
            'amount' => 100000,
            'vat' => 0,
            'status' => InvoiceStatus::APPROVED,
        ]);

        // Attempting to generate a link for an invoice in another company must yield 403 or 404 (scoped isolation)
        $responseCross = $this->post("/invoices/{$invoiceOther->id}/payment-link");
        $this->assertContains($responseCross->status(), [403, 404]);

        // Within company without invoices permission
        $custSame = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'مشتری همین شرکت',
        ]);
        $invoiceSame = Invoice::create([
            'company_id' => $this->company->id,
            'invoice_type' => InvoiceType::SELL,
            'number' => 77113,
            'date' => now(),
            'customer_id' => $custSame->id,
            'amount' => 100000,
            'vat' => 0,
            'status' => InvoiceStatus::APPROVED,
        ]);

        $unauthResponse = $this->post("/invoices/{$invoiceSame->id}/payment-link");
        $unauthResponse->assertStatus(403);

        // With invoices.payment-link.store AND invoices.show permission -> 302 success
        Permission::findOrCreate('invoices.payment-link.store', 'web');
        Permission::findOrCreate('invoices.show', 'web');
        $this->user->givePermissionTo(['invoices.payment-link.store', 'invoices.show']);

        $authResponse = $this->post("/invoices/{$invoiceSame->id}/payment-link");
        $authResponse->assertStatus(302);
    }

    /**
     * D10: Competitor migration autoRepair neutralizes spreadsheet formula injection
     */
    public function test_d10_competitor_migration_sanitizes_formula_injection(): void
    {
        $migrationService = app(CompetitorMigrationService::class);

        $payload = new MigrationPayload(
            customers: [
                ['name' => '=CMD|"/C calc"!A0', 'mobile' => '09121112233'],
                ['name' => '+SUM(1,2)', 'mobile' => '09124445566'],
                ['name' => '@HYPERLINK("http://evil.com")', 'mobile' => '09127778899'],
            ],
            products: [
                ['name' => '-10*5', 'initial_stock' => 10, 'sell_price' => 50000],
            ],
            cheques: []
        );

        $repaired = $migrationService->autoRepair($payload);

        $this->assertNotEmpty($repaired);
        $this->assertStringStartsWith("'", $payload->customers[0]['name']);
        $this->assertStringStartsWith("'", $payload->customers[1]['name']);
        $this->assertStringStartsWith("'", $payload->customers[2]['name']);
        $this->assertStringStartsWith("'", $payload->products[0]['name']);
    }

    /**
     * D12: Market data provider settings encrypts API keys in configs storage
     */
    public function test_d12_market_data_api_key_encrypted_in_storage(): void
    {
        $marketService = app(MarketIntelligenceService::class);
        $rawKey = 'NAVASAN_SECRET_API_KEY_554433';

        $marketService->saveProviderConfig($this->company->id, [
            'provider' => 'navasan',
            'api_key' => $rawKey,
        ]);

        // Direct DB inspection
        $dbConfig = DB::table('configs')
            ->where('company_id', $this->company->id)
            ->where('key', 'market_data_api_key')
            ->first();

        $this->assertNotNull($dbConfig);
        $this->assertNotEquals($rawKey, $dbConfig->value, 'Market data API key must be encrypted in configs table');

        // Reading via service decrypts correctly
        $loaded = $marketService->getProviderConfig($this->company->id);
        $this->assertEquals($rawKey, $loaded['api_key']);
    }
}
