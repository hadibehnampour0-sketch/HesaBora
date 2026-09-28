<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\ProductGroup;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SeasonalTaxReportService;
use App\Services\TaxReconciliationService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * T-5 (docs/project-context/Dakhl_TAX_MODULE_COMPLETENESS_PLAN_2026-09-25.md): the seasonal VAT
 * report re-derived independently from the ledger must agree with the invoice-derived figures.
 */
class TaxReconciliationTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9700;

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
    }

    public function test_a_normal_quarter_reconciles_cleanly_against_the_ledger(): void
    {
        [$year] = gregorian_to_jalali(now()->year, now()->month, now()->day);
        $service = app(SeasonalTaxReportService::class);
        [$start] = $service->quarterDateRange($year, 1);

        $product = $this->createProduct();
        $this->buy([array_merge($this->productItem($product, 10, 100), ['vat' => 0])], true, ++$this->nextInvoiceNumber, $start);
        $this->sell([array_merge($this->productItem($product, 1, 1000), ['vat' => 10])], true, ++$this->nextInvoiceNumber, $start);
        $this->buy([array_merge($this->productItem($product, 5, 100), ['vat' => 10])], true, ++$this->nextInvoiceNumber, $start);

        $report = $service->report($this->companyId, $year, 1);
        $reconciliation = app(TaxReconciliationService::class)->reconcileSeasonalVat($this->companyId, $report, $report['start_date'], $report['end_date']);

        $this->assertTrue($reconciliation['is_balanced']);
        $this->assertSame(100.0, $reconciliation['report_vat_collected']);
        $this->assertSame(100.0, $reconciliation['ledger_vat_collected']);
        $this->assertSame(50.0, $reconciliation['report_input_vat']);
        $this->assertSame(50.0, $reconciliation['ledger_input_vat']);
    }

    public function test_a_manual_journal_entry_on_the_vat_account_is_flagged(): void
    {
        [$year] = gregorian_to_jalali(now()->year, now()->month, now()->day);
        $service = app(SeasonalTaxReportService::class);
        [$start] = $service->quarterDateRange($year, 1);

        $product = $this->createProduct();
        $bought = $this->buy([array_merge($this->productItem($product, 10, 100), ['vat' => 0])], true, ++$this->nextInvoiceNumber, $start);
        $this->sell([array_merge($this->productItem($product, 1, 1000), ['vat' => 10])], true, ++$this->nextInvoiceNumber, $start);

        // Simulate an out-of-band manual entry directly against the VAT-payable subject.
        Transaction::create([
            'document_id' => $bought['invoice']->document_id,
            'subject_id' => (int) config('amir.sell_vat'),
            'value' => 40,
            'desc' => 'manual adjustment',
        ]);

        $report = $service->report($this->companyId, $year, 1);
        $reconciliation = app(TaxReconciliationService::class)->reconcileSeasonalVat($this->companyId, $report, $report['start_date'], $report['end_date']);

        $this->assertFalse($reconciliation['is_balanced']);
        $this->assertFalse($reconciliation['vat_collected_matches']);
        $this->assertEqualsWithDelta(-40.0, $reconciliation['vat_collected_difference'], 0.01);
    }

    public function test_another_companys_ledger_never_leaks_into_the_reconciliation(): void
    {
        [$year] = gregorian_to_jalali(now()->year, now()->month, now()->day);
        $service = app(SeasonalTaxReportService::class);
        [$start] = $service->quarterDateRange($year, 1);

        $product = $this->createProduct();
        $bought = $this->buy([array_merge($this->productItem($product, 10, 100), ['vat' => 0])], true, ++$this->nextInvoiceNumber, $start);
        $this->sell([array_merge($this->productItem($product, 1, 1000), ['vat' => 10])], true, ++$this->nextInvoiceNumber, $start);

        // A second company posts a much larger VAT amount on the exact same date, the exact same
        // (shared, config-defined) VAT subject id, but its own document/company_id -- it must
        // never be counted against company 1's reconciliation.
        $other = Company::factory()->create(['fiscal_year' => 1405]);
        $otherDocument = Document::factory()->create(['company_id' => $other->id, 'date' => $start]);
        Transaction::create([
            'document_id' => $otherDocument->id,
            'subject_id' => (int) config('amir.sell_vat'),
            'value' => 999,
            'desc' => 'other company vat',
        ]);

        $report = $service->report($this->companyId, $year, 1);
        $reconciliation = app(TaxReconciliationService::class)->reconcileSeasonalVat($this->companyId, $report, $report['start_date'], $report['end_date']);

        $this->assertTrue($reconciliation['is_balanced']);
        $this->assertSame(100.0, $reconciliation['ledger_vat_collected']);
    }
}
