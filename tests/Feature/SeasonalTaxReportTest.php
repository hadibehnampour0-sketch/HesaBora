<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\SeasonalTaxReportService;
use App\Services\TaxRuleService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * P12.3 (Seasonal Tax & VAT Reporting Engine, Article 169/ماده ۱۶۹).
 */
class SeasonalTaxReportTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9900;

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
        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'seasonal-tax.index']),
            Permission::firstOrCreate(['name' => 'seasonal-tax.export-csv']),
            Permission::firstOrCreate(['name' => 'seasonal-tax.export-pdf']),
        );
        $this->actingAs($this->user);

        app(TaxRuleService::class)->recordRule(null, 10.0, false, '2020-01-01', null, 'General');
    }

    public function test_quarter_boundaries_are_contiguous_across_the_whole_jalali_year(): void
    {
        $service = app(SeasonalTaxReportService::class);
        $year = 1403;

        $ranges = collect([1, 2, 3, 4])->map(fn ($q) => $service->quarterDateRange($year, $q));

        // Each quarter's end must be exactly one day before the next
        // quarter's start -- no gap, no overlap.
        for ($i = 0; $i < 3; $i++) {
            $end = \Carbon\Carbon::parse($ranges[$i][1]);
            $nextStart = \Carbon\Carbon::parse($ranges[$i + 1][0]);
            $this->assertTrue($end->addDay()->isSameDay($nextStart));
        }

        // Q4's end must be exactly one day before the FOLLOWING year's Q1 start.
        $q4End = \Carbon\Carbon::parse($ranges[3][1]);
        $nextYearQ1Start = \Carbon\Carbon::parse($service->quarterDateRange($year + 1, 1)[0]);
        $this->assertTrue($q4End->addDay()->isSameDay($nextYearQ1Start));

        // Q1's own start must round-trip back to Farvardin 1 of the same year.
        [$jy, $jm, $jd] = gregorian_to_jalali(...explode('-', $ranges[0][0]));
        $this->assertSame([$year, 1, 1], [$jy, $jm, $jd]);
    }

    public function test_a_taxable_sell_invoice_is_split_into_base_and_vat(): void
    {
        [$startDate] = app(SeasonalTaxReportService::class)->quarterDateRange(1403, 1);
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber, $startDate);
        $this->sell([array_merge($this->productItem($product, 1, 1000), ['vat' => 10])], true, ++$this->nextInvoiceNumber, $startDate);

        $report = app(SeasonalTaxReportService::class)->report($this->companyId, 1403, 1);

        $this->assertSame(1000.0, $report['taxable_sales']);
        $this->assertSame(100.0, $report['vat_collected']);
        $this->assertSame(0.0, $report['exempt_sales']);
    }

    public function test_a_zero_vat_sell_invoice_counts_as_exempt(): void
    {
        [$startDate] = app(SeasonalTaxReportService::class)->quarterDateRange(1403, 1);
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber, $startDate);
        $this->sell([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber, $startDate);

        $report = app(SeasonalTaxReportService::class)->report($this->companyId, 1403, 1);

        $this->assertSame(500.0, $report['exempt_sales']);
        $this->assertSame(0.0, $report['taxable_sales']);
        $this->assertSame(0.0, $report['vat_collected']);
    }

    public function test_a_buy_invoice_contributes_creditable_input_vat(): void
    {
        [$startDate] = app(SeasonalTaxReportService::class)->quarterDateRange(1403, 1);
        $product = $this->createProduct();
        $this->buy([array_merge($this->productItem($product, 10, 100), ['vat' => 10])], true, ++$this->nextInvoiceNumber, $startDate);

        $report = app(SeasonalTaxReportService::class)->report($this->companyId, 1403, 1);

        $this->assertSame(1000.0, $report['taxable_purchases']);
        $this->assertSame(100.0, $report['creditable_input_vat']);
        $this->assertSame(-100.0, $report['net_vat_payable']);
    }

    public function test_a_return_sell_reduces_taxable_sales_and_vat_collected(): void
    {
        [$startDate] = app(SeasonalTaxReportService::class)->quarterDateRange(1403, 1);
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber, $startDate);
        $sold = $this->sell([array_merge($this->productItem($product, 5, 1000), ['vat' => 10])], true, ++$this->nextInvoiceNumber, $startDate);
        $this->returnSell([array_merge($this->productItem($product, 2, 1000), ['vat' => 10])], $sold['invoice']->id, true, ++$this->nextInvoiceNumber, $startDate);

        $report = app(SeasonalTaxReportService::class)->report($this->companyId, 1403, 1);

        // net taxable sales = (5 - 2) * 1000; net VAT = (5 - 2) * 100.
        $this->assertSame(3000.0, $report['taxable_sales']);
        $this->assertSame(300.0, $report['vat_collected']);
    }

    public function test_an_invoice_outside_the_quarter_is_excluded(): void
    {
        [, $q1End] = app(SeasonalTaxReportService::class)->quarterDateRange(1403, 1);
        $outsideDate = \Carbon\Carbon::parse($q1End)->addDay()->toDateString();

        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber, $outsideDate);
        $this->sell([array_merge($this->productItem($product, 1, 1000), ['vat' => 10])], true, ++$this->nextInvoiceNumber, $outsideDate);

        $report = app(SeasonalTaxReportService::class)->report($this->companyId, 1403, 1);

        $this->assertSame(0.0, $report['taxable_sales']);
        $this->assertSame(0.0, $report['vat_collected']);
    }

    public function test_another_companys_invoices_never_appear_in_this_companys_report(): void
    {
        [$startDate] = app(SeasonalTaxReportService::class)->quarterDateRange(1403, 1);
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber, $startDate);
        $this->sell([array_merge($this->productItem($product, 1, 1000), ['vat' => 10])], true, ++$this->nextInvoiceNumber, $startDate);

        $otherCompany = Company::factory()->create();

        $report = app(SeasonalTaxReportService::class)->report($otherCompany->id, 1403, 1);

        $this->assertSame(0.0, $report['taxable_sales']);
        $this->assertSame(0.0, $report['vat_collected']);
    }

    public function test_the_index_page_renders_with_a_valid_permission(): void
    {
        [$startDate] = app(SeasonalTaxReportService::class)->quarterDateRange(1403, 1);
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber, $startDate);
        $this->sell([array_merge($this->productItem($product, 1, 1000), ['vat' => 10])], true, ++$this->nextInvoiceNumber, $startDate);

        $this->get(route('seasonal-tax.index', ['jalali_year' => 1403, 'quarter' => 1]))
            ->assertOk()
            ->assertSee(__('Seasonal Tax Report'));
    }

    public function test_csv_export_downloads_a_csv_file(): void
    {
        $response = $this->get(route('seasonal-tax.export-csv', ['jalali_year' => 1403, 'quarter' => 1]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_pdf_export_downloads_a_pdf_file(): void
    {
        $response = $this->get(route('seasonal-tax.export-pdf', ['jalali_year' => 1403, 'quarter' => 1]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }
}
