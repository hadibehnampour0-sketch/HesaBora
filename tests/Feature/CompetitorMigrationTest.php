<?php

namespace Tests\Feature;

use App\Enums\FinancialProvenance;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Product;
use App\Models\User;
use App\Services\Migration\CompetitorMigrationService;
use App\Services\Migration\DTO\MigrationPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Spatie\Permission\Models\Permission;
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
        Cache::forever('active_company_id', $this->company->id);
        Cookie::queue('active-company-id', (string) $this->company->id);
        $_COOKIE['active-company-id'] = (string) $this->company->id;

        $this->importSubjects($this->company->id);
        $this->importConfigs($this->company->id);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'migration.*']));
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

        // Test preview endpoint with SQL dump
        $sql = "INSERT INTO tbl_person (name, mobile, balance) VALUES ('آرشام صادقی', '09121112233', 15000000);";
        $sqlResponse = $this->postJson(route('migration.preview'), [
            'source_type' => 'sql_dump',
            'raw_json' => $sql,
        ]);
        $sqlResponse->assertOk();
        $sqlResponse->assertJsonPath('success', true);
        $sqlResponse->assertJsonPath('summary.customers_count', 1);

        // Test preview endpoint with auto_unknown
        $unknownJson = json_encode([
            'tbl_ashkhas_khas' => [
                ['nam' => 'کیوان بهرامی', 'tel' => '09301112233', 'mandeh' => 8000000],
            ],
        ]);
        $autoResponse = $this->postJson(route('migration.preview'), [
            'source_type' => 'auto_unknown',
            'raw_json' => $unknownJson,
        ]);
        $autoResponse->assertOk();
        $autoResponse->assertJsonPath('success', true);
        $autoResponse->assertJsonPath('summary.customers_count', 1);
    }

    public function test_sepidar_backup_parsing(): void
    {
        $sepidarData = [
            'Parties' => [
                [
                    'PartyName' => 'شرکت داروسازی رازی',
                    'PartyCode' => 'P-101',
                    'Mobile' => '09123334455',
                    'NationalCode' => '10100998877',
                    'DebitBalance' => 45000000,
                    'CreditBalance' => 0,
                    'PartyGroupTitle' => 'مشتریان عمده',
                ],
                [
                    'PartyName' => 'بسته‌بندی کیمیا',
                    'DebitBalance' => 0,
                    'CreditBalance' => 12000000,
                ],
            ],
            'Items' => [
                [
                    'ItemName' => 'شربت دیفن هیدرامین',
                    'ItemCode' => 'MED-500',
                    'Barcode' => '6260011223344',
                    'PurchasePrice' => 180000,
                    'SalePrice' => 250000,
                    'StockQuantity' => 150,
                    'UnitName' => 'شیشه',
                ],
            ],
            'Cheques' => [
                [
                    'ChequeNumber' => 'SEP-9901',
                    'SayadNumber' => '9988776655443322',
                    'PartyName' => 'شرکت داروسازی رازی',
                    'Amount' => 30000000,
                    'DueDate' => '2026-12-15',
                    'Type' => 1, // Receivable
                    'BankName' => 'بانک رفاه کارگران',
                ],
            ],
        ];

        $payload = $this->service->parse($sepidarData, 'sepidar');

        $this->assertCount(2, $payload->customers);
        $this->assertEquals('شرکت داروسازی رازی', $payload->customers[0]['name']);
        $this->assertEquals(45000000, $payload->customers[0]['balance']);
        $this->assertEquals('09123334455', $payload->customers[0]['mobile']);

        $this->assertCount(1, $payload->products);
        $this->assertEquals('شربت دیفن هیدرامین', $payload->products[0]['name']);
        $this->assertEquals(150, $payload->products[0]['initial_stock']);
        $this->assertEquals('شیشه', $payload->products[0]['unit']);

        $this->assertCount(1, $payload->cheques);
        $this->assertEquals('9988776655443322', $payload->cheques[0]['sayad_number']);
        $this->assertEquals('receivable', $payload->cheques[0]['direction']);
    }

    public function test_sql_dump_backup_parsing(): void
    {
        $sql = <<<'SQL'
INSERT INTO `tbl_ashkhas` (`id`, `c_name`, `c_mobile`, `c_melli`, `c_bed`, `c_bes`) VALUES
(1, 'محسن چاوشی', '09129998811', '0054321987', 7000000, 0),
(2, 'استودیو صدا', '02122223333', '10105556667', 0, 3000000);

INSERT INTO `tbl_kala` (`id`, `k_name`, `k_code`, `k_barcode`, `k_price_buy`, `k_price_sell`, `k_mojoodi`, `k_vahed`) VALUES
(10, 'میکروفون استودیویی', 'MIC-01', '7788990011', 120000000, 160000000, 3, 'دستگاه');

INSERT INTO `tbl_cheque` (`id`, `chq_number`, `chq_sayad`, `chq_mablagh`, `chq_tarikh_sarresid`, `chq_noe`, `chq_tarafhesab`) VALUES
(101, 'CHK-7700', '1234567890123456', 25000000, '2026-11-30', 1, 'محسن چاوشی');
SQL;

        $payload = $this->service->parse($sql, 'sql_dump');

        $this->assertCount(2, $payload->customers);
        $this->assertEquals('محسن چاوشی', $payload->customers[0]['name']);
        $this->assertEquals(7000000, $payload->customers[0]['balance']);

        $this->assertCount(1, $payload->products);
        $this->assertEquals('میکروفون استودیویی', $payload->products[0]['name']);
        $this->assertEquals(3, $payload->products[0]['initial_stock']);

        $this->assertCount(1, $payload->cheques);
        $this->assertEquals('1234567890123456', $payload->cheques[0]['sayad_number']);
        $this->assertEquals(25000000, $payload->cheques[0]['amount']);
    }

    public function test_csv_backup_parsing(): void
    {
        $csv = <<<'CSV'
نام کالا,کد کالا,بارکد,قیمت خرید,قیمت فروش,موجودی,واحد
ساعت هوشمند اپل,AW-10,0192837465,30000000,35000000,7,عدد
کاور سیلیکونی ساعت,CS-20,0192837466,200000,400000,25,عدد
CSV;

        $payload = $this->service->parse($csv, 'csv');

        $this->assertCount(2, $payload->products);
        $this->assertEquals('ساعت هوشمند اپل', $payload->products[0]['name']);
        $this->assertEquals(7, $payload->products[0]['initial_stock']);
        $this->assertEquals(30000000, $payload->products[0]['purchase_price']);
    }

    public function test_autonomous_unknown_database_smart_schema_detection(): void
    {
        // An completely unknown database from an obscure third-party software with non-standard table and column names
        $unknownDb = [
            'contacts_crm' => [
                [
                    'person_name' => 'دکتر سهراب مرادی',
                    'phone_number' => '09351234567',
                    'national_id' => '0089876543',
                    'remain' => 18000000,
                    'addr' => 'تهران، ونک، کوچه آفتاب',
                ],
                [
                    'person_name' => 'بیمارستان رازی',
                    'phone_number' => '02188776655',
                    'remain' => -5000000,
                ],
            ],
            'merchandise_warehouse' => [
                [
                    'kala_name' => 'کیت آزمایشگاهی هماتولوژی',
                    'sku' => 'LAB-88',
                    'barcodes' => '9900112233',
                    'fi_kharid' => 8500000,
                    'fi_forosh' => 11000000,
                    'quantity' => 12,
                    'unit_name' => 'بسته',
                ],
            ],
            'promissory_documents' => [
                [
                    'serial' => 'CHQ-552',
                    'sayadi' => '1122334455667788',
                    'value' => 10000000,
                    'sarresid' => '2026-12-01',
                    'kind' => '1',
                    'payer' => 'دکتر سهراب مرادی',
                ],
            ],
        ];

        $payload = $this->service->parse($unknownDb, 'auto_unknown');

        // Check that the autonomous engine detected customers from 'contacts_crm'
        $this->assertCount(2, $payload->customers);
        $this->assertEquals('دکتر سهراب مرادی', $payload->customers[0]['name']);
        $this->assertEquals('09351234567', $payload->customers[0]['mobile']);
        $this->assertEquals(18000000, $payload->customers[0]['balance']);

        // Check products from 'merchandise_warehouse'
        $this->assertCount(1, $payload->products);
        $this->assertEquals('کیت آزمایشگاهی هماتولوژی', $payload->products[0]['name']);
        $this->assertEquals(12, $payload->products[0]['initial_stock']);
        $this->assertEquals(8500000, $payload->products[0]['purchase_price']);

        // Check cheques from 'promissory_documents'
        $this->assertCount(1, $payload->cheques);
        $this->assertEquals('1122334455667788', $payload->cheques[0]['sayad_number']);
        $this->assertEquals(10000000, $payload->cheques[0]['amount']);

        // Check metadata explanations
        $this->assertArrayHasKey('inferred_mappings', $payload->metadata);
        $this->assertEquals('contacts_crm', $payload->metadata['inferred_mappings']['customers']['source_table']);
        $this->assertEquals('merchandise_warehouse', $payload->metadata['inferred_mappings']['products']['source_table']);
        $this->assertEquals('promissory_documents', $payload->metadata['inferred_mappings']['cheques']['source_table']);

        // Verify end-to-end commit with balanced journal entry
        $result = $this->service->commitMigration($this->user, $this->company->id, $payload, true);
        $this->assertTrue($result->successful);
        $this->assertEquals(2, $result->importedCustomersCount);
        $this->assertEquals(1, $result->importedProductsCount);
        $this->assertEquals(1, $result->importedChequesCount);

        // Verify perfectly balanced double-entry
        $doc = Document::withoutGlobalScopes()->with('transactions')->find($result->openingDocument->id);
        $this->assertEquals(0, $doc->transactions->sum('value'));
    }
}
