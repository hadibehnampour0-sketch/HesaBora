<?php

namespace Tests\Feature;

use App\Jobs\SyncMoadianStatusJob;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\MoadianHistory;
use App\Models\ProductGroup;
use App\Models\TaxReadinessLog;
use App\Models\User;
use App\Services\MoadianReconciliationService;
use App\Services\TaxRuleService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Jooyeshgar\Moadian\Facades\Moadian;
use Mockery;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * P12.1 (Moadian Live Status Inquiry & Auto-Reconciliation). Mocks the
 * `Moadian` facade directly for the exact same reason `MoadianServiceTest`
 * does (guardrail #45): the underlying package uses raw Guzzle, so
 * `Http::fake()` cannot intercept it, and a test here must never reach the
 * real government API regardless of `MOADIAN_BASE_URI`.
 */
class MoadianReconciliationTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 7900;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;
        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        ProductGroup::factory()->withSubjects()->create(['name' => 'g', 'vat' => 0, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'g', 'company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);

        $this->user = User::factory()->create();
        Company::find($this->companyId)->users()->syncWithoutDetaching($this->user);
        $this->actingAs($this->user);

        app(TaxRuleService::class)->recordRule(null, 10.0, false, '2020-01-01', null, 'General');
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

    private function invoiceWithPendingHistory(): \App\Models\Invoice
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 5, 50)], true, ++$this->nextInvoiceNumber);
        $invoice = $this->sell([$this->productItem($product, 1, 100)], true, ++$this->nextInvoiceNumber)['invoice'];

        MoadianHistory::create(['invoice_id' => $invoice->id, 'data' => ['status' => 'UNKNOWN', 'referenceNumber' => 'REF-1']]);

        return $this->findInvoice($invoice->id);
    }

    public function test_reconciliation_updates_status_from_pending_to_success(): void
    {
        $invoice = $this->invoiceWithPendingHistory();

        $fakeClient = Mockery::mock();
        $fakeClient->shouldReceive('inquiryByReferenceNumbers')->once()->with('REF-1')->andReturn(
            $this->fakeMoadianResponse([['status' => 'SUCCESS', 'referenceNumber' => 'REF-1']])
        );
        Moadian::shouldReceive('for')->once()->andReturn($fakeClient);

        $changed = app(MoadianReconciliationService::class)->reconcilePendingInvoices($this->companyId);

        $this->assertSame(1, $changed);
        $latest = $invoice->moadianHistories()->latest('id')->first();
        $this->assertSame('SUCCESS', $latest->data['status']);
    }

    public function test_reconciliation_does_not_recount_when_status_is_unchanged(): void
    {
        $this->invoiceWithPendingHistory();

        $fakeClient = Mockery::mock();
        $fakeClient->shouldReceive('inquiryByReferenceNumbers')->once()->andReturn(
            $this->fakeMoadianResponse([['status' => 'UNKNOWN', 'referenceNumber' => 'REF-1']])
        );
        Moadian::shouldReceive('for')->once()->andReturn($fakeClient);

        $changed = app(MoadianReconciliationService::class)->reconcilePendingInvoices($this->companyId);

        $this->assertSame(0, $changed);
    }

    public function test_an_invoice_already_successfully_sent_is_never_re_queried(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 5, 50)], true, ++$this->nextInvoiceNumber);
        $invoice = $this->sell([$this->productItem($product, 1, 100)], true, ++$this->nextInvoiceNumber)['invoice'];
        MoadianHistory::create(['invoice_id' => $invoice->id, 'data' => ['status' => 'SUCCESS', 'referenceNumber' => 'REF-2']]);

        Moadian::shouldReceive('for')->never();

        $changed = app(MoadianReconciliationService::class)->reconcilePendingInvoices($this->companyId);

        $this->assertSame(0, $changed);
    }

    public function test_reconciliation_triggers_a_tax_readiness_recalculation_on_change(): void
    {
        $this->invoiceWithPendingHistory();
        $this->assertSame(0, TaxReadinessLog::count());

        $fakeClient = Mockery::mock();
        $fakeClient->shouldReceive('inquiryByReferenceNumbers')->once()->andReturn(
            $this->fakeMoadianResponse([['status' => 'SUCCESS', 'referenceNumber' => 'REF-1']])
        );
        Moadian::shouldReceive('for')->once()->andReturn($fakeClient);

        app(MoadianReconciliationService::class)->reconcilePendingInvoices($this->companyId);

        $this->assertSame(1, TaxReadinessLog::count());
    }

    public function test_the_job_reconciles_invoices_across_every_company(): void
    {
        $invoiceA = $this->invoiceWithPendingHistory();

        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create();
        $otherCompany->users()->syncWithoutDetaching($otherUser);
        config(['active-company-id' => $otherCompany->id]);
        $this->actingAs($otherUser);
        $this->importSubjects($otherCompany->id);
        $this->importConfigs($otherCompany->id);
        ProductGroup::factory()->withSubjects()->create(['name' => 'g2', 'vat' => 0, 'company_id' => $otherCompany->id]);
        $otherCustomerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'g2', 'company_id' => $otherCompany->id]);
        Customer::factory()->withGroup($otherCustomerGroup)->withSubject()->create(['company_id' => $otherCompany->id]);
        app(TaxRuleService::class)->recordRule(null, 10.0, false, '2020-01-01', null, 'General B');

        config(['active-company-id' => $this->companyId]);
        $this->actingAs($this->user);

        $fakeClient = Mockery::mock();
        $fakeClient->shouldReceive('inquiryByReferenceNumbers')->once()->andReturn(
            $this->fakeMoadianResponse([['status' => 'SUCCESS', 'referenceNumber' => 'REF-1']])
        );
        Moadian::shouldReceive('for')->once()->andReturn($fakeClient);

        (new SyncMoadianStatusJob)->handle(app(MoadianReconciliationService::class));

        $this->assertSame('SUCCESS', $invoiceA->moadianHistories()->latest('id')->first()->data['status']);
    }
}
