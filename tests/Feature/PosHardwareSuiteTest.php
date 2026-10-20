<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\Hardware\BarcodeScannerParser;
use App\Services\Hardware\EscPosReceiptBuilder;
use App\Services\Pos\PosInventoryLockService;
use App\Services\Pos\PosTerminalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class PosHardwareSuiteTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;
    protected Company $company;
    protected Customer $customer;
    protected Product $productA;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::firstOrCreate(['id' => 1], [
            'name' => 'هایپرمارکت و فروشگاه دخل',
            'fiscal_year' => 1405,
        ]);
        \Illuminate\Support\Facades\Cache::forever('active_company_id', $this->company->id);
        \Illuminate\Support\Facades\Cookie::queue('active-company-id', (string) $this->company->id);
        $_COOKIE['active-company-id'] = (string) $this->company->id;

        $this->importSubjects($this->company->id);
        $this->importConfigs($this->company->id);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'pos.*']));
        $this->actingAs($this->user);
        $this->company->users()->syncWithoutDetaching($this->user);

        $customerGroup = CustomerGroup::factory()->withSubject()->create([
            'name' => 'مشتریان حضوری',
            'company_id' => $this->company->id,
        ]);

        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create([
            'company_id' => $this->company->id,
            'name' => 'مشتری حضوری فروشگاه',
            'mobile' => '09120000000',
        ]);

        $productGroup = ProductGroup::factory()->withSubjects()->create([
            'name' => 'سوپرمارکت',
            'company_id' => $this->company->id,
        ]);

        $this->productA = Product::create([
            'company_id' => $this->company->id,
            'name' => 'شیر پرچرب کاله',
            'code' => 'KALE-MILK',
            'barcode' => '6260123456789',
            'group' => $productGroup->id,
            'selling_price' => 450000,
            'quantity' => 10,
        ]);

        $this->productB = Product::create([
            'company_id' => $this->company->id,
            'name' => 'پنیر تبریز وزنی',
            'code' => '00145',
            'barcode' => '2000145000000',
            'group' => $productGroup->id,
            'selling_price' => 3000000, // per kg
            'quantity' => 20, // 20 kg
        ]);
    }

    public function test_esc_pos_receipt_builder_generates_valid_byte_stream(): void
    {
        $builder = new EscPosReceiptBuilder(42);

        $builder->initialize()
            ->addHeader('هایپرمارکت دخل', 'صندوق: POS-01', '1001', '2026-10-20')
            ->addItemRow('شیر پرچرب کاله', 2, 450000, 900000)
            ->addSummary(900000, payable: 900000)
            ->openCashDrawer()
            ->addFooter()
            ->cutPaper();

        $bytes = $builder->getRawBytes();

        // Must contain initialize ESC @
        $this->assertStringContainsString(EscPosReceiptBuilder::ESC . '@', $bytes);

        // Must contain cash drawer kick pulse ESC p 0 25 250
        $this->assertStringContainsString(EscPosReceiptBuilder::ESC . "p\x00\x19\xFA", $bytes);

        // Must contain paper cut command GS V
        $this->assertStringContainsString(EscPosReceiptBuilder::GS . "V\x00", $bytes);

        // Must contain Persian text content
        $this->assertStringContainsString('هایپرمارکت دخل', $bytes);
        $this->assertStringContainsString('شیر پرچرب کاله', $bytes);

        // Valid base64 output
        $base64 = $builder->getBase64();
        $this->assertNotEmpty($base64);
        $this->assertEquals($bytes, base64_decode($base64));
    }

    public function test_barcode_scanner_parser_standard_and_weighted_supermarket_items(): void
    {
        $parser = new BarcodeScannerParser;

        // 1. Standard piece item barcode
        $resA = $parser->parse('6260123456789');
        $this->assertFalse($resA->isWeighted);
        $this->assertEquals(1.0, $resA->quantity);
        $this->assertEquals('6260123456789', $resA->productCode);

        // 2. Weighted scale barcode (Prefix 20 + 5 digits code 00145 + 5 digits weight 01450 grams + check digit 8)
        $resB = $parser->parse('2000145014508');
        $this->assertTrue($resB->isWeighted);
        $this->assertEquals('145', $resB->productCode);
        $this->assertEquals(1450, $resB->weightInGrams);
        $this->assertEquals(1.45, $resB->getWeightInKilograms());
        $this->assertEquals(1.45, $resB->quantity);

        // 3. Price-embedded barcode (Prefix 21)
        $resC = $parser->parse('2100145050001');
        $this->assertFalse($resC->isWeighted);
        $this->assertEquals(5000000, $resC->embeddedPrice);
    }

    public function test_pos_inventory_lan_locking_and_multi_terminal_reservation(): void
    {
        $lockService = app(PosInventoryLockService::class);
        $companyId = $this->company->id;
        $warehouseId = 1;
        $productId = $this->productA->id; // Total physical quantity: 10

        // Initial available stock
        $this->assertEquals(10, $lockService->getAvailableStock($companyId, $warehouseId, $productId));

        // Terminal 1 reserves 6 units
        $res1 = $lockService->reserveStock($companyId, $warehouseId, $productId, 6, 'POS-01');
        $this->assertTrue($res1);

        // Terminal 2 now sees only 4 units available
        $availableForTerm2 = $lockService->getAvailableStock($companyId, $warehouseId, $productId, 'POS-02');
        $this->assertEquals(4, $availableForTerm2);

        // Terminal 2 tries to reserve 5 units -> should be rejected!
        $res2 = $lockService->reserveStock($companyId, $warehouseId, $productId, 5, 'POS-02');
        $this->assertFalse($res2);

        // Terminal 1 releases reservation -> Terminal 2 sees full 10 units again!
        $lockService->releaseReservation($companyId, $warehouseId, $productId, 'POS-01');
        $this->assertEquals(10, $lockService->getAvailableStock($companyId, $warehouseId, $productId, 'POS-02'));
    }

    public function test_pos_fast_checkout_decrements_stock_creates_invoice_and_receipt(): void
    {
        $service = app(PosTerminalService::class);

        $checkoutData = [
            'terminal_id' => 'POS-01',
            'warehouse_id' => 1,
            'customer_id' => $this->customer->id,
            'open_drawer' => true,
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 2,
                    'price' => 450000,
                    'discount' => 0,
                ],
                [
                    'product_id' => $this->productB->id,
                    'quantity' => 1.5, // 1.5 kg cheese
                    'price' => 3000000,
                    'discount' => 0,
                ],
            ],
        ];

        $result = $service->fastCheckout($this->user, $this->company->id, $checkoutData);

        $this->assertTrue($result->successful);
        $this->assertNotNull($result->invoice);
        $this->assertNotEmpty($result->receiptBase64);

        // 2 * 450,000 + 1.5 * 3,000,000 = 900,000 + 4,500,000 = 5,400,000
        $this->assertEquals(5400000, (float) $result->invoice->amount);
        $this->assertEquals(InvoiceStatus::APPROVED, $result->invoice->status);

        // Verify product stock decremented
        $this->productA->refresh();
        $this->assertEquals(8, $this->productA->quantity); // 10 - 2

        $this->productB->refresh();
        $this->assertEquals(18.5, $this->productB->quantity); // 20 - 1.5

        // Verify ESC/POS receipt contains open cash drawer command
        $rawBytes = base64_decode($result->receiptBase64);
        $this->assertStringContainsString(EscPosReceiptBuilder::ESC . "p\x00\x19\xFA", $rawBytes);
        $this->assertStringContainsString('شیر پرچرب کاله', $rawBytes);
    }

    public function test_pos_http_endpoints_scan_and_checkout(): void
    {
        // 1. Scan endpoint with barcode
        $scanResponse = $this->postJson(route('pos.scan'), [
            'barcode' => '6260123456789',
            'terminal_id' => 'POS-01',
        ]);

        $scanResponse->assertOk();
        $scanResponse->assertJsonPath('success', true);
        $scanResponse->assertJsonPath('item.name', 'شیر پرچرب کاله');
        $scanResponse->assertJsonPath('item.unit_price', 450000);

        // 2. Scan endpoint with weighted scale barcode
        $scanWeighted = $this->postJson(route('pos.scan'), [
            'barcode' => '2000145012507', // 1,250 grams
            'terminal_id' => 'POS-01',
        ]);

        $scanWeighted->assertOk();
        $scanWeighted->assertJsonPath('success', true);
        $scanWeighted->assertJsonPath('item.name', 'پنیر تبریز وزنی');
        $scanWeighted->assertJsonPath('item.quantity', 1.25);

        // 3. Checkout endpoint
        $checkoutResponse = $this->postJson(route('pos.checkout'), [
            'terminal_id' => 'POS-01',
            'open_drawer' => true,
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 1,
                    'price' => 450000,
                ],
            ],
        ]);

        $checkoutResponse->assertOk();
        $checkoutResponse->assertJsonPath('success', true);
        $this->assertNotNull($checkoutResponse->json('receipt_base64'));
    }
}
