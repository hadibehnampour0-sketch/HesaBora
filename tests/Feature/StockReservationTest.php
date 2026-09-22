<?php

namespace Tests\Feature;

use App\Enums\StockReservationStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\StockReservation;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use App\Services\InvoiceFulfillmentService;
use App\Services\StockReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * P4.1-P4.3 (Operational Depth), Epic Pack 3/Master Phase. Built as a
 * separate additive layer (owner's explicit choice): these tests
 * specifically verify that reservations/receipts/deliveries never touch
 * WarehouseProductStock.quantity -- the existing Invoice-approval-driven
 * stock movement (WarehouseInvoiceStockTest, ReturnInvoiceCOGSTest, etc.)
 * remains completely unaffected.
 */
class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected Product $product;

    protected Warehouse $warehouse;

    protected Customer $customer;

    protected StockReservationService $service;

    protected InvoiceFulfillmentService $fulfillmentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        config(['active-company-id' => $this->company->id]);
        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);
        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->company->id]);

        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->company->id]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->company->id]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->company->id]);
        $this->warehouse = Warehouse::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN']);
        $this->product = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->company->id,
            'quantity' => 0,
            'average_cost' => 100,
        ]);
        WarehouseProductStock::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id],
            ['quantity' => 100, 'average_cost' => 100]
        );

        $this->service = app(StockReservationService::class);
        $this->fulfillmentService = app(InvoiceFulfillmentService::class);
    }

    public function test_reserving_stock_reduces_available_but_never_touches_on_hand_quantity(): void
    {
        $this->service->reserve($this->product, $this->warehouse, 30, null, $this->user);

        $this->assertEqualsWithDelta(100.0, $this->service->onHandQuantity($this->product, $this->warehouse), 0.001);
        $this->assertEqualsWithDelta(30.0, $this->service->reservedQuantity($this->product, $this->warehouse), 0.001);
        $this->assertEqualsWithDelta(70.0, $this->service->availableQuantity($this->product, $this->warehouse), 0.001);

        // The actual, authoritative stock table is completely unaffected.
        $this->assertEqualsWithDelta(100.0, (float) WarehouseProductStock::where('product_id', $this->product->id)->value('quantity'), 0.001);
    }

    public function test_reserving_more_than_available_is_rejected(): void
    {
        $this->service->reserve($this->product, $this->warehouse, 80, null, $this->user);

        $this->expectException(ValidationException::class);
        $this->service->reserve($this->product, $this->warehouse, 30, null, $this->user);
    }

    public function test_releasing_a_reservation_frees_up_availability_again(): void
    {
        $reservation = $this->service->reserve($this->product, $this->warehouse, 40, null, $this->user);

        $this->service->release($reservation);

        $this->assertEqualsWithDelta(100.0, $this->service->availableQuantity($this->product, $this->warehouse), 0.001);
        $this->assertSame(StockReservationStatus::RELEASED, $reservation->fresh()->status);
    }

    public function test_consuming_a_reservation_marks_it_consumed_and_does_not_double_count_toward_reserved(): void
    {
        $reservation = $this->service->reserve($this->product, $this->warehouse, 40, null, $this->user);

        $this->service->consume($reservation);

        $this->assertSame(StockReservationStatus::CONSUMED, $reservation->fresh()->status);
        // A consumed reservation is no longer ACTIVE, so it no longer
        // counts against availability (the real deduction already
        // happened via the existing, unrelated Invoice-approval flow).
        $this->assertEqualsWithDelta(100.0, $this->service->availableQuantity($this->product, $this->warehouse), 0.001);
    }

    public function test_an_already_released_reservation_cannot_be_released_or_consumed_again(): void
    {
        $reservation = $this->service->reserve($this->product, $this->warehouse, 10, null, $this->user);
        $this->service->release($reservation);

        $this->expectException(ValidationException::class);
        $this->service->consume($reservation->fresh());
    }

    private function makeInvoice(int $type): Invoice
    {
        return Invoice::create([
            'customer_id' => $this->customer->id,
            'number' => random_int(1, 999999),
            'date' => now(),
            'invoice_type' => $type,
            'status' => 1,
            'amount' => 1000,
            'vat' => 0,
        ]);
    }

    public function test_recording_a_goods_receipt_for_a_buy_invoice(): void
    {
        $invoice = $this->makeInvoice(1); // BUY

        $receipt = $this->fulfillmentService->recordGoodsReceipt($invoice, [], $this->user);

        $this->assertSame($invoice->id, $receipt->invoice_id);
    }

    public function test_recording_a_goods_receipt_for_a_sell_invoice_is_rejected(): void
    {
        $invoice = $this->makeInvoice(2); // SELL

        $this->expectException(ValidationException::class);
        $this->fulfillmentService->recordGoodsReceipt($invoice, [], $this->user);
    }

    public function test_recording_a_second_goods_receipt_for_the_same_invoice_is_rejected(): void
    {
        $invoice = $this->makeInvoice(1);
        $this->fulfillmentService->recordGoodsReceipt($invoice, [], $this->user);

        $this->expectException(ValidationException::class);
        $this->fulfillmentService->recordGoodsReceipt($invoice, [], $this->user);
    }

    public function test_recording_a_delivery_note_for_a_sell_invoice(): void
    {
        $invoice = $this->makeInvoice(2); // SELL

        $note = $this->fulfillmentService->recordDelivery($invoice, [], $this->user);

        $this->assertSame($invoice->id, $note->invoice_id);
    }

    public function test_recording_a_delivery_note_for_a_buy_invoice_is_rejected(): void
    {
        $invoice = $this->makeInvoice(1); // BUY

        $this->expectException(ValidationException::class);
        $this->fulfillmentService->recordDelivery($invoice, [], $this->user);
    }

    // --- Cross-company isolation -------------------------------------

    public function test_company_a_cannot_release_company_bs_reservation(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);

        $role = Role::firstOrCreate(['name' => 'Super-Admin']);
        $actorA = User::factory()->create();
        $actorA->companies()->syncWithoutDetaching([$companyA->id]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(0);
        $actorA->assignRole($role);
        $registrar->setPermissionsTeamId(null);

        $previous = config('active-company-id');
        config(['active-company-id' => $companyB->id]);
        $groupB = ProductGroup::factory()->withSubjects()->create(['company_id' => $companyB->id]);
        $warehouseB = Warehouse::create(['company_id' => $companyB->id, 'name' => 'B', 'code' => 'B']);
        $productB = Product::factory()->withGroup($groupB)->withSubjects()->create(['company_id' => $companyB->id, 'quantity' => 0, 'average_cost' => 100]);
        WarehouseProductStock::create(['warehouse_id' => $warehouseB->id, 'product_id' => $productB->id, 'quantity' => 50, 'average_cost' => 100]);
        $reservationB = $this->service->reserve($productB, $warehouseB, 10, null, $this->user);
        config(['active-company-id' => $previous]);

        $this->actingAs($actorA)
            ->withCookie('active-company-id', (string) $companyA->id)
            ->post(route('stock-reservations.release', $reservationB))
            ->assertNotFound();

        $this->assertSame(StockReservationStatus::ACTIVE, $reservationB->fresh()->status);
    }

    public function test_company_a_cannot_record_a_goods_receipt_for_company_bs_invoice(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);

        $role = Role::firstOrCreate(['name' => 'Super-Admin']);
        $actorA = User::factory()->create();
        $actorA->companies()->syncWithoutDetaching([$companyA->id]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(0);
        $actorA->assignRole($role);
        $registrar->setPermissionsTeamId(null);

        $previous = config('active-company-id');
        config(['active-company-id' => $companyB->id]);
        $customerB = Customer::factory()->create();
        $invoiceB = Invoice::create([
            'customer_id' => $customerB->id, 'number' => 7001, 'date' => now(),
            'invoice_type' => 1, 'status' => 1, 'amount' => 1000, 'vat' => 0,
        ]);
        config(['active-company-id' => $previous]);

        $this->actingAs($actorA)
            ->withCookie('active-company-id', (string) $companyA->id)
            ->post(route('invoices.goods-receipt.store', $invoiceB), [])
            ->assertNotFound();

        $this->assertSame(0, \App\Models\GoodsReceipt::withoutGlobalScopes()->where('invoice_id', $invoiceB->id)->count());
    }
}
