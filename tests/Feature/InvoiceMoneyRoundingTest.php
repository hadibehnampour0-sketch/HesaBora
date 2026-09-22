<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Product;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\ProductGroup;
use App\Models\Transaction;
use App\Models\User;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * Money/Decimal Safety bounded hardening (post-D-088). Empirically confirmed
 * SQLite (every automated test's database) does not enforce `decimal(x,2)`
 * precision at all -- it silently stores full float precision -- while
 * MySQL/MariaDB (production) rounds each column independently at insert
 * time. `InvoiceTransactionBuilder::extractItemValues()` computed VAT (a
 * percentage multiplication) and item amount with no explicit rounding
 * anywhere, meaning: (1) the exact stored value differed between test and
 * production for the same input, invisibly, and (2) rounding N related
 * values independently after the fact does not guarantee their sum stays
 * exactly zero, threatening the SUM(transactions.value) = 0 balanced-
 * document invariant. This test proves the fix: rounding at the single
 * point of multiplication, before any aggregation, so the balance holds by
 * construction on every database engine.
 */
class InvoiceMoneyRoundingTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9990;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;
        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        ProductGroup::factory()->withSubjects()->create(['name' => 'g', 'vat' => 9, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'g', 'company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);
    }

    /** Item with an explicit VAT percentage, unlike InvoiceTestHelper::productItem() which always uses vat=0. */
    private function itemWithVat(Product $product, int $qty, float $unit, float $vatPercent): array
    {
        return [
            'itemable_type' => 'product',
            'itemable_id' => $product->id,
            'warehouse_id' => $product->warehouseStocks()->orderBy('warehouse_id')->value('warehouse_id'),
            'quantity' => $qty,
            'unit' => $unit,
            'unit_discount' => 0,
            'vat' => $vatPercent,
        ];
    }

    public function test_a_fractional_vat_percentage_still_balances_to_exactly_zero(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 500)], true, ++$this->nextInvoiceNumber);

        // unit = 142.86, vat = 9% -> raw itemVat = 12.8574, raw itemAmount
        // = 142.86 -- exactly the "more than 2 decimals before rounding"
        // shape this fix targets.
        $result = $this->createInvoice(
            InvoiceType::SELL,
            [$this->itemWithVat($product, 1, 142.86, 9)],
            true,
            ++$this->nextInvoiceNumber
        );

        $invoice = $this->findInvoice($result['invoice']->id);
        $documentId = $invoice->document_id;
        $this->assertNotNull($documentId);
        $sum = (float) Transaction::where('document_id', $documentId)->sum('value');

        // 1e-9 tolerance absorbs only genuine IEEE-754 float representation
        // noise (observed ~1e-13 here) -- the original bug this test guards
        // against was on the order of 1e-4 to 1e-2 (real cents), which this
        // tolerance would still catch.
        $this->assertEqualsWithDelta(0.0, $sum, 1e-9, 'transactions.value must sum to (float-noise-only) zero, not be off by real cents.');

        // invoice->amount is totalAmount + totalVat - subtractions (the
        // full grand total), not just the item amount alone.
        $this->assertEqualsWithDelta(155.72, (float) $invoice->amount, 0.0001);
        $this->assertEqualsWithDelta(12.86, (float) $invoice->vat, 0.0001);
    }

    public function test_stored_transaction_values_have_no_more_than_two_decimal_places(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 500)], true, ++$this->nextInvoiceNumber);

        $result = $this->createInvoice(
            InvoiceType::SELL,
            [$this->itemWithVat($product, 3, 111.11, 7)],
            true,
            ++$this->nextInvoiceNumber
        );

        $invoice = $this->findInvoice($result['invoice']->id);
        $documentId = $invoice->document_id;
        $this->assertNotNull($documentId);
        $values = Transaction::where('document_id', $documentId)->pluck('value');

        foreach ($values as $value) {
            $rounded = round((float) $value, 2);
            $this->assertEqualsWithDelta($rounded, (float) $value, 0.000001, "Transaction value {$value} has more than 2 decimal places.");
        }

        $this->assertEqualsWithDelta(0.0, (float) $values->sum(), 1e-9);
    }

    /**
     * Phase 5 cross-module check: the fix rounds each item independently
     * before aggregation, so this proves that property still holds once
     * several differently-shaped fractional lines (different unit prices,
     * quantities and VAT percentages) are summed into one document, not
     * just a single line in isolation.
     */
    public function test_multiple_fractional_line_items_still_balance_to_exactly_zero(): void
    {
        $productA = $this->createProduct();
        $productB = $this->createProduct();
        $productC = $this->createProduct();
        $this->buy([$this->productItem($productA, 10, 500)], true, ++$this->nextInvoiceNumber);
        $this->buy([$this->productItem($productB, 10, 500)], true, ++$this->nextInvoiceNumber);
        $this->buy([$this->productItem($productC, 10, 500)], true, ++$this->nextInvoiceNumber);

        $result = $this->createInvoice(
            InvoiceType::SELL,
            [
                $this->itemWithVat($productA, 3, 111.11, 7),
                $this->itemWithVat($productB, 2, 142.86, 9),
                $this->itemWithVat($productC, 5, 33.33, 3),
            ],
            true,
            ++$this->nextInvoiceNumber
        );

        $invoice = $this->findInvoice($result['invoice']->id);
        $documentId = $invoice->document_id;
        $this->assertNotNull($documentId);
        $values = Transaction::where('document_id', $documentId)->pluck('value');

        foreach ($values as $value) {
            $rounded = round((float) $value, 2);
            $this->assertEqualsWithDelta($rounded, (float) $value, 0.000001, "Transaction value {$value} has more than 2 decimal places.");
        }

        $this->assertEqualsWithDelta(0.0, (float) $values->sum(), 1e-9, 'Multi-line aggregation must still balance to (float-noise-only) zero.');
    }

    /** Item with a fractional quantity -- InvoiceTestHelper::productItem() type-hints `int $qty`. */
    private function itemWithFractionalQuantity(Product $product, float $qty, float $unit): array
    {
        return [
            'itemable_type' => 'product',
            'itemable_id' => $product->id,
            'warehouse_id' => $product->warehouseStocks()->orderBy('warehouse_id')->value('warehouse_id'),
            'quantity' => $qty,
            'unit' => $unit,
            'unit_discount' => 0,
            'vat' => 0,
        ];
    }

    /**
     * Batch 2 (Money/Decimal Safety at Financial Boundaries) finding:
     * `extractItemValues()`'s own docblock claimed unit_price*quantity and
     * the VAT calculation were the only two multiplications that could
     * introduce more than 2 decimal places -- disproven empirically.
     * `InvoiceTransactionBuilder::buildSellItemsTransactions()` computes
     * the Cost-of-Goods-Sold/Inventory lines as `average_cost * quantity`
     * completely separately, never routed through the Phase 2 fix. Since
     * `invoice_items.quantity` is a real `decimal(10,2)` column (fractional
     * quantities like weight/volume-based products are a real, supported
     * case, not an edge case), a clean 2-decimal average_cost (e.g. 109.99)
     * times a fractional quantity (e.g. 0.33) produces a 4-decimal value
     * (36.2967) that DocumentService::createDocument()'s own validation
     * rejects outright -- confirmed by temporarily reverting the fix
     * (`InvoiceTransactionBuilder::pushTransaction()`'s centralizing
     * `round()`) and observing the exact rejection, then restoring it.
     */
    public function test_a_fractional_sell_quantity_against_a_clean_average_cost_still_balances(): void
    {
        $product = $this->createProduct();
        // 1 unit at 109.99 -> average_cost becomes exactly 109.99 (clean).
        $this->buy([$this->itemWithFractionalQuantity($product, 1, 109.99)], true, ++$this->nextInvoiceNumber);

        // Selling a fractional quantity means average_cost * quantity =
        // 109.99 * 0.33 = 36.2967 -- more than 2 decimals before rounding.
        $result = $this->createInvoice(
            InvoiceType::SELL,
            [$this->itemWithFractionalQuantity($product, 0.33, 200)],
            true,
            ++$this->nextInvoiceNumber
        );

        $invoice = $this->findInvoice($result['invoice']->id);
        $documentId = $invoice->document_id;
        $this->assertNotNull($documentId);
        $values = Transaction::where('document_id', $documentId)->pluck('value');

        foreach ($values as $value) {
            $rounded = round((float) $value, 2);
            $this->assertEqualsWithDelta($rounded, (float) $value, 0.000001, "Transaction value {$value} has more than 2 decimal places.");
        }

        $this->assertEqualsWithDelta(0.0, (float) $values->sum(), 1e-9, 'COG/Inventory lines from a fractional quantity must still balance to zero.');
    }
}
