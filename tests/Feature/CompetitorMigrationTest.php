<?php

namespace Tests\Feature;

use App\Enums\FinancialProvenance;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\Subject;
use App\Models\User;
use App\Services\Migration\CompetitorMigrationService;
use App\Services\Migration\DTO\MigrationPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class CompetitorMigrationTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;
    protected Company $company;
    protected CompetitorMigrationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::firstOrCreate(['id' => 1], [
            'name' => 'شرکت تست مهاجرت',
            'fiscal_year' => 1405,
        ]);
        \Illuminate\Support\Facades\Cache::forever('active_company_id', $this->company->id);
        \Illuminate\Support\Facades\Cookie::queue('active-company-id', (string) $this->company->id);
        $_COOKIE['active-company-id'] = (string) $this->company->id;

        $this->importSubjects($this->company->id);
        $this->importConfigs($this->company->id);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $this->user->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'migration.*']));
        $this->actingAs($this->user);
        $this->company->users()->syncWithoutDetaching($this->user);

        $this->service = app(CompetitorMigrationService::class);
    }

    public function test_holoo_backup_parsing(): void
    {
        $holooData = [
            'TarafHesab' => [
                [
                    'C_Name' => 'حسین پناهی',
                    'C_Mobile' => '09121112233',
                    'C_Melli' => '0012345678',
                    'C_Bed' => 5000000,
                    'C_Bes' => 0,
                ],
                [
                    'C_Name' => 'تامین کننده شفق',
                    'C_Mobile' => '02188889999',
                    'C_Melli' => '10101010101',
                    'C_Bed' => 0,
                    'C_Bes' => 3000000,
                ],
            ],
            'Kala' => [
                [
                    'K_Name' => 'لپ‌تاپ لنوو تینک‌پد',
                    'K_Code' => 'LNV-01',
                    'K_Price_Buy' => 200000000,
                    'K_Price_Sell' => 250000000,
                    'K_Mojoodi' => 5,
                ],
            ],
            'Cheque' => [
                [
                    'Chq_Number' => '889900',
                    'Chq_Sayad' => '1122334455667788',
                    'Chq_TarafHesab' => 'حسین پناهی',
                    'Chq_Mablagh' => 15000000,
                    'Chq_Tarikh_Sarresid' => '2026-11-20',
                    'Chq_Noe' => 1, // 1: دریافتی
                ],
            ],
        ];

        $payload = $this->service->parse($holooData, 'holoo');

        $this->assertCount(2, $payload->customers);
        $this->assertEquals('حسین پناهی', $payload->customers[0]['name']);
        $this->assertEquals(5000000, $payload->customers[0]['balance']);

        $this->assertCount(1, $payload->products);
        $this->assertEquals('لپ‌تاپ لنوو تینک‌پد', $payload->products[0]['name']);
        $this->assertEquals(5, $payload->products[0]['initial_stock']);

        $this->assertCount(1, $payload->cheques);
        $this->assertEquals('1122334455667788', $payload->cheques[0]['sayad_number']);
        $this->assertEquals('receivable', $payload->cheques[0]['direction']);
    }

    public function test_health_check_detects_negative_stock_and_computes_reconciliation(): void
    {
        $payload = new MigrationPayload(
            customers: [
                ['name' => 'شرکت آلفا', 'balance' => 10000000, 'mobile' => '09120000000'],
                ['name' => 'تامین کننده بتا', 'balance' => -4000000, 'mobile' => '09121111111'],
            ],
            products: [
                ['name' => 'مانیتور سامسونگ', 'purchase_price' => 50000000, 'sell_price' => 60000000, 'initial_stock' => 2],
                ['name' => 'کیبورد بی‌سیم', 'purchase_price' => 2000000, 'sell_price' => 3000000, 'initial_stock' => -3],
            ],
            cheques: [
                ['cheque_number' => '101', 'amount' => 8000000, 'direction' => 'receivable', 'sayad_number' => '1234567890123456'],
            ]
        );

        $health = $this->service->analyzeAndHealthCheck($payload);

        $this->assertTrue($health->hasWarnings());
        $this->assertStringContainsString('موجودی منفی', $health->warnings[0]['message']);

        $recon = $health->reconciliation;
        $this->assertEquals(10000000, $recon['total_debtors']);
        $this->assertEquals(4000000, $recon['total_creditors']);
        $this->assertEquals(100000000, $recon['total_inventory_valuation']); // 2 * 50,000,000 (negative ignored in assets)
        $this->assertEquals(8000000, $recon['total_receivable_cheques']);

        // Assets = 10,000,000 (debtors) + 100,000,000 (inventory) + 8,000,000 (cheques) = 118,000,000
        $this->assertEquals(118000000, $recon['total_assets']);
        // Liabilities = 4,000,000 (creditors)
        $this->assertEquals(4000000, $recon['total_liabilities']);
        // Equity = 118,000,000 - 4,000,000 = 114,000,000
        $this->assertEquals(114000000, $recon['opening_equity']);
    }

    public function test_commit_migration_creates_entities_and_balanced_document_with_baseline_provenance(): void
    {
        $payload = new MigrationPayload(
            customers: [
                ['name' => 'بهرام رادان', 'balance' => 20000000, 'mobile' => '09121234567'],
                ['name' => 'شرکت بازرگانی کاسپین', 'balance' => -8000000, 'mobile' => '09127654321'],
            ],
            products: [
                ['name' => 'پرینتر حرارتی بیکسلون', 'code' => 'BIX-101', 'purchase_price' => 15000000, 'sell_price' => 18000000, 'initial_stock' => 4],
            ],
            cheques: [
                ['cheque_number' => 'CHK-5501', 'amount' => 12000000, 'direction' => 'receivable', 'sayad_number' => '1122334455667788', 'customer_name' => 'بهرام رادان'],
            ]
        );

        $result = $this->service->commitMigration($this->user, $this->company->id, $payload, true);

        $this->assertTrue($result->successful);
        $this->assertEquals(2, $result->importedCustomersCount);
        $this->assertEquals(1, $result->importedProductsCount);
        $this->assertEquals(1, $result->importedChequesCount);

        // Verify Customer was created in DB
        $this->assertDatabaseHas('customers', [
            'company_id' => $this->company->id,
            'name' => 'بهرام رادان',
            'mobile' => '09121234567',
        ]);

        // Verify Product was created in DB
        $this->assertDatabaseHas('products', [
            'company_id' => $this->company->id,
            'name' => 'پرینتر حرارتی بیکسلون',
            'code' => 'BIX-101',
            'quantity' => 4,
        ]);

        // Verify Cheque was created in DB
        $this->assertDatabaseHas('cheques', [
            'company_id' => $this->company->id,
            'cheque_number' => 'CHK-5501',
            'amount' => 12000000,
        ]);

        // Verify Opening Document
        $openingDoc = $result->openingDocument;
        $this->assertNotNull($openingDoc);
        $this->assertNotNull($openingDoc->approved_at);

        // CRITICAL (D-085): Imported historical documents MUST be tagged FinancialProvenance::BASELINE
        $this->assertEquals(FinancialProvenance::BASELINE, $openingDoc->provenance);

        // CRITICAL: Double-entry invariant verification (sum of values must be EXACTLY 0)
        $docWithTx = Document::withoutGlobalScopes()->with('transactions')->find($openingDoc->id);
        $sum = $docWithTx->transactions->sum('value');
        $this->assertEquals(0, $sum, 'The opening journal entry must be perfectly balanced!');
    }

    public function test_migration_http_endpoints(): void
    {
        $json = json_encode([
            'customers' => [
                ['name' => 'مشتری آزمایشی وب', 'balance' => 5000000, 'mobile' => '09129998877'],
            ],
            'products' => [
                ['name' => 'کالای آزمایشی وب', 'purchase_price' => 1000000, 'sell_price' => 1500000, 'initial_stock' => 10],
            ],
            'cheques' => [],
        ]);

        // Test preview endpoint
        $previewResponse = $this->postJson(route('migration.preview'), [
            'source_type' => 'universal_json',
            'raw_json' => $json,
        ]);

        $previewResponse->assertOk();
        $previewResponse->assertJsonPath('success', true);
        $previewResponse->assertJsonPath('summary.customers_count', 1);
        $previewResponse->assertJsonPath('summary.products_count', 1);

        // Test execute endpoint
        $executeResponse = $this->postJson(route('migration.execute'), [
            'source_type' => 'universal_json',
            'raw_json' => $json,
            'auto_repair' => true,
        ]);

        $executeResponse->assertOk();
        $executeResponse->assertJsonPath('success', true);
        $executeResponse->assertJsonPath('imported_customers', 1);
        $executeResponse->assertJsonPath('imported_products', 1);
    }
}
