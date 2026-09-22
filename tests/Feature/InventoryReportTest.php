<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use App\Services\InventoryReportService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * P4.7/P4.8 (Inventory Valuation, Slow-Moving Stock), Epic Pack 3/Master
 * Phase. Pure read reports computed from the existing
 * WarehouseProductStock/InvoiceItem tables -- no new schema.
 */
class InventoryReportTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    protected Company $company;

    protected User $user;

    protected Customer $customer;

    protected Warehouse $warehouse;

    protected InventoryReportService $service;

    protected int $nextInvoiceNumber = 6000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        config(['active-company-id' => $this->company->id]);
        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);
        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->company->id]);

        $this->importSubjects($this->company->id);
        $this->importConfigs($this->company->id);

        $customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->company->id]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->company->id]);
        $this->warehouse = Warehouse::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN']);
        $this->service = app(InventoryReportService::class);
    }

    private function makeProduct(float $quantity, float $averageCost): Product
    {
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->company->id]);
        $product = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->company->id,
            'quantity' => 0,
            'average_cost' => $averageCost,
        ]);
        WarehouseProductStock::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_id' => $product->id],
            ['quantity' => $quantity, 'average_cost' => $averageCost]
        );

        return $product;
    }

    private function sell(Product $product, float $quantity, string $date): void
    {
        $number = ++$this->nextInvoiceNumber;
        // createInvoice(..., approved: true) only fully posts a Document
        // immediately for BUY/return types -- a SELL invoice is left at
        // READY_TO_APPROVE and needs its own explicit changeInvoiceStatus()
        // call (see WarehouseInvoiceStockTest/DomainBusinessScenariosTest
        // for the same established two-step pattern).
        $result = InvoiceService::createInvoice(
            $this->user,
            [
                'title' => 'sell', 'date' => $date, 'invoice_type' => InvoiceType::SELL,
                'customer_id' => $this->customer->id, 'warehouse_id' => $this->warehouse->id,
                'document_number' => $number, 'number' => $number,
            ],
            [['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => $quantity, 'unit' => 500, 'unit_discount' => 0, 'vat' => 0]],
            true
        );

        (new InvoiceService)->changeInvoiceStatus($result['invoice'], 'approved');
    }

    public function test_valuation_report_computes_quantity_times_average_cost_per_product_and_a_grand_total(): void
    {
        $this->makeProduct(10, 100); // 1000
        $this->makeProduct(5, 200);  // 1000

        $report = $this->service->valuationReport();

        $this->assertCount(2, $report['lines']);
        $this->assertEqualsWithDelta(2000.0, $report['total_value'], 0.01);
    }

    public function test_valuation_report_excludes_zero_quantity_stock(): void
    {
        $this->makeProduct(0, 100);
        $this->makeProduct(10, 50);

        $report = $this->service->valuationReport();

        $this->assertCount(1, $report['lines']);
    }

    public function test_valuation_report_can_be_filtered_to_a_single_warehouse(): void
    {
        $otherWarehouse = Warehouse::create(['company_id' => $this->company->id, 'name' => 'Other', 'code' => 'OTHER']);
        $product = $this->makeProduct(10, 100);
        WarehouseProductStock::create(['warehouse_id' => $otherWarehouse->id, 'product_id' => $product->id, 'quantity' => 20, 'average_cost' => 100]);

        $report = $this->service->valuationReport($this->warehouse);

        $this->assertCount(1, $report['lines']);
        $this->assertEqualsWithDelta(10.0, $report['lines']->first()['quantity'], 0.01);
    }

    public function test_a_never_sold_product_with_stock_appears_in_the_slow_moving_report(): void
    {
        $this->makeProduct(10, 100);

        $report = $this->service->slowMovingReport(90);

        $this->assertCount(1, $report);
        $this->assertNull($report->first()['last_sold_at']);
    }

    public function test_a_recently_sold_product_does_not_appear_in_the_slow_moving_report(): void
    {
        $product = $this->makeProduct(10, 100);
        $this->sell($product, 2, now()->subDays(5)->toDateString());

        $report = $this->service->slowMovingReport(90);

        $this->assertCount(0, $report);
    }

    public function test_a_product_last_sold_beyond_the_threshold_appears_in_the_slow_moving_report(): void
    {
        $product = $this->makeProduct(10, 100);
        $this->sell($product, 2, now()->subDays(120)->toDateString());

        $report = $this->service->slowMovingReport(90);

        $this->assertCount(1, $report);
        $this->assertGreaterThanOrEqual(90, $report->first()['days_since_last_sale']);
    }

    public function test_slow_moving_report_is_sorted_oldest_first(): void
    {
        $productA = $this->makeProduct(10, 100);
        $productB = $this->makeProduct(10, 100);
        $this->sell($productA, 1, now()->subDays(100)->toDateString());
        $this->sell($productB, 1, now()->subDays(200)->toDateString());

        $report = $this->service->slowMovingReport(90);

        $this->assertSame($productB->id, $report->first()['product_id']);
    }

    // --- Cross-company isolation -------------------------------------

    public function test_valuation_report_never_includes_another_companys_stock(): void
    {
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $previous = config('active-company-id');
        config(['active-company-id' => $companyB->id]);
        $groupB = ProductGroup::factory()->withSubjects()->create(['company_id' => $companyB->id]);
        $warehouseB = Warehouse::create(['company_id' => $companyB->id, 'name' => 'B', 'code' => 'B']);
        $productB = Product::factory()->withGroup($groupB)->withSubjects()->create(['company_id' => $companyB->id, 'quantity' => 0, 'average_cost' => 999]);
        WarehouseProductStock::create(['warehouse_id' => $warehouseB->id, 'product_id' => $productB->id, 'quantity' => 999, 'average_cost' => 999]);
        config(['active-company-id' => $previous]);

        $this->makeProduct(10, 100);

        $report = $this->service->valuationReport();

        $this->assertCount(1, $report['lines']);
        $this->assertEqualsWithDelta(1000.0, $report['total_value'], 0.01);
    }
}
