<?php

namespace Tests\Feature;

use App\Enums\SerialNumberStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductSerialNumber;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProductTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * P4.4-P4.6 (Barcode, Lot/Batch tracking, Serial numbers), Epic Pack
 * 3/Master Phase. Additive sub-unit tracking layer; never reads or gates
 * ProductService's existing quantity/COGS logic.
 */
class ProductTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected Product $product;

    protected Warehouse $warehouse;

    protected ProductTrackingService $service;

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
        $this->warehouse = Warehouse::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN']);
        $this->product = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->company->id,
            'quantity' => 0,
            'average_cost' => 100,
        ]);
        $this->service = app(ProductTrackingService::class);
    }

    // --- P4.4 Barcode ---------------------------------------------------

    public function test_a_product_can_be_looked_up_by_barcode(): void
    {
        $this->product->update(['barcode' => '0123456789012']);

        $found = Product::whereBarcode('0123456789012')->first();

        $this->assertNotNull($found);
        $this->assertSame($this->product->id, $found->id);
    }

    public function test_two_products_in_the_same_company_cannot_share_a_barcode(): void
    {
        $this->product->update(['barcode' => 'DUP-001']);
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->company->id]);
        $second = Product::factory()->withGroup($group)->withSubjects()->create(['company_id' => $this->company->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $second->update(['barcode' => 'DUP-001']);
    }

    public function test_multiple_products_without_a_barcode_do_not_violate_the_unique_constraint(): void
    {
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->company->id]);
        $second = Product::factory()->withGroup($group)->withSubjects()->create(['company_id' => $this->company->id]);

        // Neither product has set a barcode (both NULL) -- must not
        // collide, since SQL NULL is never equal to NULL under UNIQUE.
        $this->assertNull($this->product->barcode);
        $this->assertNull($second->barcode);
    }

    // --- P4.5 Lot/Batch tracking -----------------------------------------

    public function test_a_lot_can_be_received_and_listed_in_fefo_order(): void
    {
        $this->service->receiveLot($this->product, $this->warehouse, 'LOT-A', 10, now()->subDays(2)->toDateString(), now()->addDays(5)->toDateString());
        $this->service->receiveLot($this->product, $this->warehouse, 'LOT-B', 10, now()->subDay()->toDateString(), now()->addDays(2)->toDateString());

        $lots = $this->service->lotsForProduct($this->product, $this->warehouse);

        $this->assertCount(2, $lots);
        // LOT-B expires sooner -- FEFO puts it first despite being
        // received later.
        $this->assertSame('LOT-B', $lots->first()->lot_number);
    }

    public function test_the_same_lot_number_cannot_be_registered_twice_for_the_same_product(): void
    {
        $this->service->receiveLot($this->product, $this->warehouse, 'LOT-A', 10, now()->toDateString());

        $this->expectException(ValidationException::class);
        $this->service->receiveLot($this->product, $this->warehouse, 'LOT-A', 5, now()->toDateString());
    }

    public function test_consuming_from_a_lot_reduces_its_quantity_but_never_below_zero(): void
    {
        $lot = $this->service->receiveLot($this->product, $this->warehouse, 'LOT-A', 10, now()->toDateString());

        $this->service->consumeFromLot($lot, 6);

        $this->assertEqualsWithDelta(4.0, (float) $lot->fresh()->quantity, 0.0001);

        $this->expectException(ValidationException::class);
        $this->service->consumeFromLot($lot->fresh(), 5);
    }

    // --- P4.6 Serial numbers ---------------------------------------------

    public function test_a_serial_number_can_be_registered_and_marked_sold(): void
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::create([
            'customer_id' => $customer->id, 'number' => 1, 'date' => now(),
            'invoice_type' => 2, 'status' => 1, 'amount' => 1000, 'vat' => 0,
        ]);

        $serial = $this->service->registerSerial($this->product, $this->warehouse, 'SN-001');
        $this->assertSame(SerialNumberStatus::IN_STOCK, $serial->status);

        $sold = $this->service->markSold($serial, $invoice);

        $this->assertSame(SerialNumberStatus::SOLD, $sold->status);
        $this->assertSame($invoice->id, $sold->invoice_id);
        $this->assertNull($sold->warehouse_id);
    }

    public function test_a_duplicate_serial_number_is_rejected(): void
    {
        $this->service->registerSerial($this->product, $this->warehouse, 'SN-001');

        $this->expectException(ValidationException::class);
        $this->service->registerSerial($this->product, $this->warehouse, 'SN-001');
    }

    public function test_an_already_sold_serial_cannot_be_sold_again(): void
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::create([
            'customer_id' => $customer->id, 'number' => 1, 'date' => now(),
            'invoice_type' => 2, 'status' => 1, 'amount' => 1000, 'vat' => 0,
        ]);
        $serial = $this->service->registerSerial($this->product, $this->warehouse, 'SN-001');
        $this->service->markSold($serial, $invoice);

        $this->expectException(ValidationException::class);
        $this->service->markSold($serial->fresh(), $invoice);
    }

    public function test_a_returned_serial_goes_back_to_in_stock(): void
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::create([
            'customer_id' => $customer->id, 'number' => 1, 'date' => now(),
            'invoice_type' => 2, 'status' => 1, 'amount' => 1000, 'vat' => 0,
        ]);
        $serial = $this->service->registerSerial($this->product, $this->warehouse, 'SN-001');
        $this->service->markSold($serial, $invoice);

        $returned = $this->service->markReturned($serial->fresh(), $this->warehouse);

        $this->assertSame(SerialNumberStatus::IN_STOCK, $returned->status);
        $this->assertNull($returned->invoice_id);
        $this->assertSame($this->warehouse->id, $returned->warehouse_id);
    }

    // --- Cross-company isolation -------------------------------------

    public function test_company_a_cannot_mark_company_bs_serial_as_sold(): void
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
        $serialB = $this->service->registerSerial($productB, $warehouseB, 'SN-B-001');
        $customerB = Customer::factory()->create();
        $invoiceB = Invoice::create([
            'customer_id' => $customerB->id, 'number' => 9001, 'date' => now(),
            'invoice_type' => 2, 'status' => 1, 'amount' => 1000, 'vat' => 0,
        ]);
        config(['active-company-id' => $previous]);

        $this->actingAs($actorA)
            ->withCookie('active-company-id', (string) $companyA->id)
            ->post(route('product-serial-numbers.sell', $serialB), ['invoice_id' => $invoiceB->id])
            ->assertNotFound();

        $this->assertSame(SerialNumberStatus::IN_STOCK, $serialB->fresh()->status);
    }
}
