<?php

namespace Tests\Feature;

use App\Enums\CustomerType;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\MoadianHistory;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\MoadianService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Jooyeshgar\Moadian\Facades\Moadian;
use Mockery;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * P9 hardening pass. The `jooyeshgar/moadian` package's underlying client
 * uses raw Guzzle internally, not Laravel's `Http` facade -- `Http::fake()`
 * cannot intercept it. Every test here mocks the `Moadian` FACADE itself
 * (`Moadian::shouldReceive('for')->andReturn(...)`), so `MoadianService`
 * never constructs a real `Jooyeshgar\Moadian\Moadian` client and no test
 * in this file can ever reach the live production tax-authority endpoint,
 * regardless of what `MOADIAN_BASE_URI` is configured to.
 */
class MoadianServiceTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 8500;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;

        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        ProductGroup::factory()->withSubjects()->create(['name' => 'g', 'vat' => 0, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'g', 'company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create([
            'company_id' => $this->companyId,
            'type' => CustomerType::LEGAL_ENTITY,
            'ecnmcs_code' => '1234567890',
            'personal_code' => '0987654321',
            'postal_code' => '1111111111',
        ]);

        $this->user = User::factory()->create();
        Company::find($this->companyId)->users()->syncWithoutDetaching($this->user);
        $this->actingAs($this->user);
    }

    private function configureCredentials(): void
    {
        Storage::disk('local')->put('moadian/key.pem', 'fake-private-key');
        Storage::disk('local')->put('moadian/cert.pem', 'fake-certificate');

        Company::find($this->companyId)->update([
            'moadian_username' => 'A00000000',
            'tax_id' => '11111111111',
            'private_key_path' => 'moadian/key.pem',
            'certificate_path' => 'moadian/cert.pem',
        ]);
    }

    private function approvedSellInvoice(): Invoice
    {
        $product = $this->createProduct(['sstid' => '111111111']);
        $this->buy([$this->productItem($product, 5, 50)], true, ++$this->nextInvoiceNumber);
        $result = $this->sell([$this->productItem($product, 1, 100)], true, ++$this->nextInvoiceNumber);

        return $this->findInvoice($result['invoice']->id);
    }

    private function fakeMoadianResponse(array $body): object
    {
        return new class($body)
        {
            public function __construct(private array $body) {}

            public function getBody(): array
            {
                return $this->body;
            }
        };
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -----------------------------------------------------------------
    // validateSendMoadian() -- pure business-rule checks, no external call
    // -----------------------------------------------------------------

    public function test_validation_rejects_an_unapproved_invoice(): void
    {
        $product = $this->createProduct(['sstid' => '111111111']);
        $this->buy([$this->productItem($product, 5, 50)], true, ++$this->nextInvoiceNumber);
        $result = $this->sell([$this->productItem($product, 1, 100)], false, ++$this->nextInvoiceNumber);
        $invoice = $this->findInvoice($result['invoice']->id);

        $decision = app(MoadianService::class)->validateSendMoadian($invoice);

        $this->assertTrue($decision->hasErrors());
    }

    public function test_validation_rejects_a_buy_invoice(): void
    {
        $product = $this->createProduct(['sstid' => '111111111']);
        $result = $this->buy([$this->productItem($product, 5, 50)], true, ++$this->nextInvoiceNumber);
        $invoice = $this->findInvoice($result['invoice']->id);

        $decision = app(MoadianService::class)->validateSendMoadian($invoice);

        $this->assertTrue($decision->hasErrors());
    }

    public function test_validation_rejects_missing_moadian_credentials(): void
    {
        $invoice = $this->approvedSellInvoice();

        $decision = app(MoadianService::class)->validateSendMoadian($invoice);

        $this->assertTrue($decision->hasErrors());
    }

    public function test_validation_rejects_an_invoice_missing_sstid(): void
    {
        $this->configureCredentials();
        $product = $this->createProduct(['sstid' => null]);
        $this->buy([$this->productItem($product, 5, 50)], true, ++$this->nextInvoiceNumber);
        $result = $this->sell([$this->productItem($product, 1, 100)], true, ++$this->nextInvoiceNumber);
        $invoice = $this->findInvoice($result['invoice']->id);

        $decision = app(MoadianService::class)->validateSendMoadian($invoice);

        $this->assertTrue($decision->hasErrors());
    }

    public function test_validation_rejects_an_invoice_already_successfully_sent(): void
    {
        $this->configureCredentials();
        $invoice = $this->approvedSellInvoice();
        MoadianHistory::create(['invoice_id' => $invoice->id, 'data' => ['status' => 'SUCCESS']]);

        $decision = app(MoadianService::class)->validateSendMoadian($invoice->fresh());

        $this->assertTrue($decision->hasErrors());
    }

    public function test_validation_passes_for_a_fully_valid_approved_sell_invoice(): void
    {
        $this->configureCredentials();
        $invoice = $this->approvedSellInvoice();

        $decision = app(MoadianService::class)->validateSendMoadian($invoice);

        $this->assertFalse($decision->hasErrors());
    }

    // -----------------------------------------------------------------
    // sendInvoice() -- mocked facade, never touches the network
    // -----------------------------------------------------------------

    public function test_send_invoice_records_a_success_history_row_and_never_calls_the_real_network(): void
    {
        $this->configureCredentials();
        $invoice = $this->approvedSellInvoice();

        $fakeClient = Mockery::mock();
        $fakeClient->shouldReceive('sendInvoice')->once()->andReturn(
            $this->fakeMoadianResponse(['result' => [['status' => 'SUCCESS', 'referenceNumber' => 'REF-123', 'taxid' => 'TAX-1']]])
        );
        Moadian::shouldReceive('for')->once()->andReturn($fakeClient);

        $result = app(MoadianService::class)->sendInvoice($invoice);

        $this->assertTrue($result);
        $this->assertDatabaseHas('moadian_histories', [
            'invoice_id' => $invoice->id,
        ]);
        $history = MoadianHistory::where('invoice_id', $invoice->id)->latest()->first();
        $this->assertSame('SUCCESS', $history->data['status']);
    }

    public function test_send_invoice_records_a_failed_history_row_when_the_client_throws(): void
    {
        $this->configureCredentials();
        $invoice = $this->approvedSellInvoice();

        $fakeClient = Mockery::mock();
        $fakeClient->shouldReceive('sendInvoice')->once()->andThrow(new \RuntimeException('network unreachable'));
        Moadian::shouldReceive('for')->once()->andReturn($fakeClient);

        $result = app(MoadianService::class)->sendInvoice($invoice);

        $this->assertFalse($result);
        $history = MoadianHistory::where('invoice_id', $invoice->id)->latest()->first();
        $this->assertSame('FAILED', $history->data['status']);
        $this->assertStringContainsString('network unreachable', $history->data['error']);
    }

    public function test_moadian_status_records_a_history_row_from_the_mocked_inquiry(): void
    {
        $this->configureCredentials();
        $invoice = $this->approvedSellInvoice();

        $fakeClient = Mockery::mock();
        $fakeClient->shouldReceive('inquiryByReferenceNumbers')->once()->with('REF-123')->andReturn(
            $this->fakeMoadianResponse([['status' => 'SUCCESS', 'referenceNumber' => 'REF-123']])
        );
        Moadian::shouldReceive('for')->once()->andReturn($fakeClient);

        $status = app(MoadianService::class)->moadianStatus('REF-123', $invoice);

        $this->assertSame('SUCCESS', $status['status']);
        $this->assertDatabaseHas('moadian_histories', ['invoice_id' => $invoice->id]);
    }

    public function test_moadian_status_records_a_failed_history_row_when_the_client_throws(): void
    {
        $this->configureCredentials();
        $invoice = $this->approvedSellInvoice();

        $fakeClient = Mockery::mock();
        $fakeClient->shouldReceive('inquiryByReferenceNumbers')->once()->andThrow(new \RuntimeException('timeout'));
        Moadian::shouldReceive('for')->once()->andReturn($fakeClient);

        $status = app(MoadianService::class)->moadianStatus('REF-123', $invoice);

        $this->assertSame('FAILED', $status['status']);
    }
}
