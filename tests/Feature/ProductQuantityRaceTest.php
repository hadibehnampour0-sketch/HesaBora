<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\ProductGroup;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use App\Services\ProductService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config as ConfigFacade;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\MariaDbRaceTestCase;
use Tests\Helpers\SeederHelper;

/**
 * Workstream S (Concurrency), Phase 3, Area 1 -- Inventory Movement Audit.
 * `ProductService::addProductsQuantities()`/`subProductsQuantities()`
 * correctly lock the per-warehouse `WarehouseProductStock` row
 * (`adjustForInvoice()`'s own `lockForUpdate()`), but the DENORMALIZED
 * running total on `products.quantity` right next to it was structurally
 * INCONSISTENT with that sibling code: a plain, non-locking
 * `Product::find()` read followed by `+=`/`save()`, right next to a
 * correctly-locked read for the exact same conceptual operation.
 *
 * HONEST FINDING, not a confirmed exploitable bug like #7-#11: extensive
 * testing with two real MariaDB connections (multiple deliberately
 * different interleavings -- an artificial early snapshot on `products`
 * itself, and the more realistic case of an early LOCKING read on a
 * different, untouched row within the same transaction, matching what
 * `adjustForInvoice()` naturally does first) never produced silent data
 * corruption, fixed or not. MariaDB's own REPEATABLE READ consistency
 * checking already detects a transaction attempting to write a row that
 * changed since an earlier read within that same transaction, and raises
 * a real error 1020 ("Record has changed since last read") rather than
 * silently accepting a stale-based value -- this held even against the
 * UNFIXED code in the realistic-ordering scenario below. A "does it
 * block?" test also cannot distinguish fixed from unfixed here (an early
 * draft proved this: it "passed" even unfixed, since ANY UPDATE to the
 * same row takes MySQL's ordinary exclusive lock regardless of whether
 * the preceding read used `lockForUpdate()`).
 *
 * The fix (locking the product row, matching the sibling
 * `WarehouseProductStock` pattern exactly) is kept anyway as defensive,
 * structurally-consistent hardening -- it causes no regressions, and it
 * protects a plausible future scenario this test cannot itself construct
 * (this method being reached with no other lock/read earlier in the same
 * transaction). This test's job is narrower and honest about it: prove
 * neither the fixed nor unfixed code EVER silently commits a wrong
 * value -- the outcome must always be either the correct combined total
 * or a recognized, retriable consistency error, never a silent 70
 * instead of 100.
 */
class ProductQuantityRaceTest extends MariaDbRaceTestCase
{
    use InvoiceTestHelper, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;

        config(['active-company-id' => $this->companyId]);
        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        ProductGroup::factory()->withSubjects()->create(['name' => 'g', 'vat' => 0, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'g', 'company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);

        ConfigFacade::set('database.connections.mysql_a', ConfigFacade::get('database.connections.mysql'));
        ConfigFacade::set('database.connections.mysql_b', ConfigFacade::get('database.connections.mysql'));
        DB::purge('mysql_a');
        DB::purge('mysql_b');
    }

    /**
     * Verified against BOTH the fixed and the reverted-to-plain-read code
     * -- neither ever silently commits 70 (the wrong, stale-based total).
     * The realistic ordering used here (B's snapshot established via its
     * own natural early lockForUpdate() read on an untouched row, not an
     * artificial read on `products` itself) does not reproduce silent
     * corruption on the unfixed code either; see the class docblock for
     * the full account of what was and wasn't provable here.
     */
    public function test_second_stock_addition_reading_a_stale_snapshot_does_not_lose_the_first_additions_quantity_delta(): void
    {
        $product = $this->createProduct(['quantity' => 0]);
        $productId = $product->id;

        // A second warehouse for the SAME product, so the two additions'
        // per-warehouse WarehouseProductStock updates land on DIFFERENT
        // rows -- isolating this test from that (already correctly
        // locked) row's own contention.
        $warehouseA = Warehouse::withoutGlobalScopes()->where('company_id', $this->companyId)->first()
            ?? Warehouse::withoutGlobalScopes()->create(['name' => 'Main Warehouse', 'code' => 'MAIN', 'company_id' => $this->companyId]);
        WarehouseProductStock::firstOrCreate(['warehouse_id' => $warehouseA->id, 'product_id' => $productId], ['quantity' => 0, 'average_cost' => 0]);

        $warehouseB = Warehouse::withoutGlobalScopes()->create(['name' => 'Second Warehouse', 'code' => 'WH2', 'company_id' => $this->companyId]);
        WarehouseProductStock::create(['warehouse_id' => $warehouseB->id, 'product_id' => $productId, 'quantity' => 0, 'average_cost' => 0]);

        $itemA = [
            'itemable_type' => \App\Models\Product::class,
            'itemable_id' => $productId,
            'warehouse_id' => $warehouseA->id,
            'quantity' => 30,
        ];
        $itemB = [
            'itemable_type' => \App\Models\Product::class,
            'itemable_id' => $productId,
            'warehouse_id' => $warehouseB->id,
            'quantity' => 70,
        ];

        // Connection B establishes its REPEATABLE READ snapshot via its
        // OWN natural first touch of the schema -- exactly what
        // adjustForInvoice() does for real (a lockForUpdate() read of its
        // OWN warehouse's stock row, untouched by A) -- BEFORE connection
        // A commits its +30. This is deliberately NOT an artificial
        // throwaway read on `products` itself (an earlier draft of this
        // test used one, which turned out to make ANY later touch of
        // `products` -- fixed or not -- hit MariaDB's error 1020
        // consistency check, unable to distinguish the two).
        ConfigFacade::set('database.default', 'mysql_b');
        DB::connection('mysql_b')->beginTransaction();
        WarehouseProductStock::on('mysql_b')->where('warehouse_id', $warehouseB->id)->where('product_id', $productId)->lockForUpdate()->first();

        // Connection A applies its own +30 directly via the real service
        // method and commits.
        ConfigFacade::set('database.default', 'mysql_a');
        DB::connection('mysql_a')->beginTransaction();
        ProductService::addProductsQuantities([$itemA], InvoiceType::BUY);
        DB::connection('mysql_a')->commit();

        $committedAfterA = (float) DB::table('products')->where('id', $productId)->value('quantity');
        $this->assertSame(30.0, $committedAfterA, 'connection A must have committed its own +30 before B proceeds');

        // Connection B now applies its own +70 within its ALREADY-OPEN
        // transaction (snapshot fixed before A's commit). Two safe
        // outcomes exist and both are accepted: B's locked read either
        // (a) gets MariaDB's own latest-committed value under FOR UPDATE
        // and correctly computes 30+70=100, or (b) MariaDB detects the
        // cross-table snapshot conflict this creates and raises a
        // retriable error (never silently applying a stale-based
        // result). What must NEVER happen is B silently computing and
        // committing 70 -- discarding A's already-committed +30 with no
        // error at all.
        ConfigFacade::set('database.default', 'mysql_b');
        $safeOutcome = true;
        try {
            ProductService::addProductsQuantities([$itemB], InvoiceType::BUY);
            DB::connection('mysql_b')->commit();
        } catch (\Illuminate\Database\QueryException|\Illuminate\Database\DeadlockException $e) {
            $safeOutcome = str_contains($e->getMessage(), '1020') || str_contains($e->getMessage(), 'Lock wait timeout') || str_contains($e->getMessage(), 'Deadlock');
        } finally {
            ConfigFacade::set('database.default', 'mysql');
            DB::purge('mysql');
        }

        $this->assertTrue($safeOutcome, 'connection B must either succeed with the correct combined total or fail with a recognized, retriable consistency error -- never silently commit a stale-based value');

        $finalQuantity = (float) DB::table('products')->where('id', $productId)->value('quantity');
        $this->assertContains($finalQuantity, [30.0, 100.0], 'the product quantity must never silently settle on 70 (B\'s stale-snapshot-based total, discarding A\'s already-committed +30) -- it must be either 30 (B safely rejected, A\'s value intact) or 100 (B correctly combined with A\'s committed value)');
    }
}
