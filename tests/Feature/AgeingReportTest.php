<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\User;
use App\Services\AgeingService;
use App\Services\PaymentService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class AgeingReportTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9500;

    protected PaymentService $paymentService;

    protected AgeingService $ageingService;

    protected ?int $cashBoxSubjectId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentService = app(PaymentService::class);
        $this->ageingService = app(AgeingService::class);

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;

        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'ageing.*']));
        $this->actingAs($this->user);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        ProductGroup::factory()->withSubjects()->create(['name' => 'عمومی', 'vat' => 10, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'عمومی', 'description' => 'گروه مشتریان عمومی', 'company_id' => $this->companyId]);

        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);
    }

    private function cashSubjectId(): int
    {
        if ($this->cashBoxSubjectId !== null) {
            return $this->cashBoxSubjectId;
        }

        $cashBook = Subject::withoutGlobalScopes()->find((int) config('amir.cash_book'));
        $box = Subject::factory()->withParent($cashBook)->create([
            'name' => 'صندوق',
            'company_id' => $this->companyId,
        ]);

        return $this->cashBoxSubjectId = (int) $box->id;
    }

    public function test_unpaid_sell_invoice_is_bucketed_by_age(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber);
        $date = Carbon::today()->subDays(45)->toDateString();
        $this->sell([$this->productItem($product, 1, 1000)], true, ++$this->nextInvoiceNumber, $date);

        $report = $this->ageingService->report(InvoiceType::SELL);

        $this->assertEqualsWithDelta(1000, $report['total'], 0.01);
        $this->assertEqualsWithDelta(1000, $report['buckets'][1]['amount'], 0.01); // 31-60 days bucket
        $this->assertEqualsWithDelta(0, $report['buckets'][0]['amount'], 0.01);
    }

    public function test_fully_paid_invoice_is_excluded_from_report(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber);
        $sell = $this->sell([$this->productItem($product, 1, 1000)], true, ++$this->nextInvoiceNumber)['invoice'];
        $invoice = $this->findInvoice($sell->id);

        $this->paymentService->createPayment($this->user, $invoice, ['amount' => 1000, 'subject_id' => $this->cashSubjectId()]);

        $report = $this->ageingService->report(InvoiceType::SELL);
        $this->assertEqualsWithDelta(0, $report['total'], 0.01);
        $this->assertCount(0, $report['parties']);
    }

    public function test_partial_allocation_reduces_the_bucketed_remaining_amount(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 2, 500)], true, ++$this->nextInvoiceNumber);
        $sell = $this->sell([$this->productItem($product, 2, 1000)], true, ++$this->nextInvoiceNumber)['invoice'];
        $invoice = $this->findInvoice($sell->id);

        $payment = $this->paymentService->createCreditPayment($this->user, $this->customer, [
            'amount' => 500,
            'subject_id' => $this->cashSubjectId(),
        ]);
        $this->paymentService->allocatePayment($this->user, $payment, $invoice, 500);

        $report = $this->ageingService->report(InvoiceType::SELL);
        $this->assertEqualsWithDelta(1500, $report['total'], 0.01);
    }

    public function test_overdue_worklist_excludes_recent_invoices(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 2, 500)], true, ++$this->nextInvoiceNumber);

        $this->sell([$this->productItem($product, 1, 1000)], true, ++$this->nextInvoiceNumber, Carbon::today()->toDateString());
        $this->sell([$this->productItem($product, 1, 1000)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(40)->toDateString());

        $overdue = $this->ageingService->overdueWorklist(InvoiceType::SELL);

        $this->assertCount(1, $overdue);
        $this->assertGreaterThan(30, $overdue[0]['age_days']);
    }

    public function test_buy_invoices_are_reported_as_payables(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(10)->toDateString());

        $report = $this->ageingService->report(InvoiceType::BUY);
        $this->assertEqualsWithDelta(500, $report['total'], 0.01);
        $this->assertEqualsWithDelta(500, $report['buckets'][0]['amount'], 0.01);
    }

    public function test_statement_shows_chronological_running_balance(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 3, 500)], true, ++$this->nextInvoiceNumber);

        $this->sell([$this->productItem($product, 1, 1000)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(20)->toDateString());
        $this->sell([$this->productItem($product, 1, 1000)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(10)->toDateString());

        $lines = $this->ageingService->statement($this->customer, InvoiceType::SELL);

        $this->assertCount(2, $lines);
        $this->assertEqualsWithDelta(1000, $lines[0]['running_balance'], 0.01);
        $this->assertEqualsWithDelta(2000, $lines[1]['running_balance'], 0.01);
    }

    public function test_receivables_and_payables_pages_render(): void
    {
        $this->get(route('ageing.receivables'))->assertOk();
        $this->get(route('ageing.payables'))->assertOk();
    }

    public function test_statement_page_renders(): void
    {
        $this->get(route('ageing.statement', ['customer' => $this->customer->id, 'type' => 'receivables']))->assertOk();
    }
}
