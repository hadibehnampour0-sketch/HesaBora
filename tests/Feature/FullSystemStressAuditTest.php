<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\AnalyticDimension;
use App\Models\AncillaryCost;
use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Cheque;
use App\Models\Chequebook;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerWallet;
use App\Models\DailySettlement;
use App\Models\DistributionVisitor;
use App\Models\Document;
use App\Models\Employee;
use App\Models\FinancingPlan;
use App\Models\FixedAsset;
use App\Models\Invoice;
use App\Models\InvoicePrintTemplate;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductMarketPrice;
use App\Models\ProductionOrder;
use App\Models\ProductionRecipe;
use App\Models\SalesRoute;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\Subject;
use App\Models\TradeExchange;
use App\Models\User;
use App\Models\VisitPlan;
use App\Models\Warehouse;
use App\Models\WorkShift;
use App\Models\WorkSite;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class FullSystemStressAuditTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    protected User $adminUser;
    protected Company $company;
    protected array $auditResults = [
        'routes_tested' => 0,
        'passed_routes' => [],
        'failed_routes' => [],
        'dead_links' => [],
        'unbound_buttons' => [],
        'missing_translations' => [],
        'mock_or_incomplete' => [],
        'redundant_or_orphan' => [],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create company and user
        $this->company = Company::factory()->create([
            'name' => 'شرکت توسعه بازرگانی سهند اروند (سهامی خاص)',
            'fiscal_year' => 1405,
        ]);

        $this->adminUser = User::factory()->create([
            'name' => 'مدیر ارشد سیستم',
            'username' => 'arad_admin',
            'email' => 'admin@sahand-holding.ir',
        ]);
        $this->adminUser->companies()->attach($this->company->id);

        // Grant super admin permission and all permissions
        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $allPermissions = Permission::all();
        $this->adminUser->givePermissionTo($allPermissions);
        $this->adminUser->assignRole($role);

        // 2. Set Cookies and Config
        $this->withCookies(['active-company-id' => (string) $this->company->id]);
        $_COOKIE['active-company-id'] = (string) $this->company->id;
        config(['active-company-id' => $this->company->id, 'active-company-fiscal-year' => 1405]);

        // 3. Import subjects & configs
        $this->importSubjects($this->company->id);
        $this->importConfigs($this->company->id);
    }

    /**
     * Seed comprehensive real Iranian business data across all modules
     */
    protected function seedComprehensiveRealData(): array
    {
        $companyId = $this->company->id;

        // A. Warehouses
        $mainWarehouse = Warehouse::create([
            'company_id' => $companyId,
            'name' => 'انبار مرکزی غرب تهران',
            'code' => 'WH-WEST',
            'address' => 'تهران، کیلومتر ۱۱ جاده مخصوص کرج',
            'phone' => '02144901234',
        ]);
        $retailWarehouse = Warehouse::create([
            'company_id' => $companyId,
            'name' => 'انبار فروشگاه مرکزی بازار',
            'code' => 'WH-BAZAAR',
            'address' => 'تهران، بازار بزرگ، سرای امید',
            'phone' => '02155609876',
        ]);

        // B. Product Groups & Products
        $digitalGroup = ProductGroup::create(['name' => 'کالای دیجیتال و موبایل', 'company_id' => $companyId]);
        $appliancesGroup = ProductGroup::create(['name' => 'لوازم خانگی برقی', 'company_id' => $companyId]);

        $phone = Product::create([
            'company_id' => $companyId,
            'group' => $digitalGroup->id,
            'code' => 'PRD-S24-ULTRA',
            'barcode' => '8806095321012',
            'name' => 'گوشی سامسونگ گلکسی S24 اولترا ۲۵۶ گیگ',
            'quantity' => 15,
            'average_cost' => 620000000, // 62M Tomans
            'selling_price' => 695000000,
            'oversell' => 1,
            'unit' => 'عدد',
            'vat' => 10,
        ]);

        $laptop = Product::create([
            'company_id' => $companyId,
            'group' => $digitalGroup->id,
            'code' => 'PRD-MBP-M3',
            'barcode' => '195949123456',
            'name' => 'لپ‌تاپ اپل مک‌بوک پرو ۱۴ اینچ M3 Pro',
            'quantity' => 8,
            'average_cost' => 1100000000,
            'selling_price' => 1240000000,
            'oversell' => 1,
            'unit' => 'دستگاه',
            'vat' => 10,
        ]);

        // Market Price tracking
        ProductMarketPrice::create([
            'company_id' => $companyId,
            'product_id' => $phone->id,
            'market_price' => 690000000,
            'market_source_type' => 'web_link',
            'market_source_url' => 'https://digikala.com/product/dkp-12345',
            'is_wholesale_broadcast' => true,
            'wholesale_price' => 670000000,
            'wholesale_min_qty' => 3,
            'wholesale_note' => 'پلمپ شرکت با گارانتی ۱۸ ماهه معتبر',
            'guild_category' => 'digital',
            'last_checked_at' => Carbon::now(),
        ]);

        // C. Services & Service Groups
        $serviceGroup = ServiceGroup::create(['name' => 'خدمات گارانتی و نصب', 'company_id' => $companyId]);
        $service = Service::create([
            'company_id' => $companyId,
            'group' => $serviceGroup->id,
            'code' => 'SRV-INSTALL',
            'name' => 'خدمات راه‌اندازی و نصب اولیه در محل',
            'selling_price' => 15000000,
            'vat' => 10,
        ]);

        // D. Customer Groups & Customers
        $vipGroup = CustomerGroup::create(['name' => 'مشتریان عمده VIP', 'company_id' => $companyId]);
        $customer1 = Customer::create([
            'company_id' => $companyId,
            'name' => 'شرکت مهندسی ارتباطات پارس پرداز',
            'code' => 'CUST-PARS',
            'mobile' => '09123456789',
            'phone' => '02188776655',
            'national_code' => '10103456789', // شناسه ملی
            'postal_code' => '1431612345',
            'address' => 'تهران، خیابان ولیعصر، بالاتر از میدان ونک، برج نگار، طبقه ۱۲',
            'group' => $vipGroup->id,
            'balance' => 0,
        ]);
        $customer2 = Customer::create([
            'company_id' => $companyId,
            'name' => 'حاج قاسم محمدی (تجهیزات بازار)',
            'code' => 'CUST-MOHAMMADI',
            'mobile' => '09121112233',
            'national_code' => '0071234567',
            'group' => $vipGroup->id,
            'balance' => 50000000,
        ]);

        // Wallet
        CustomerWallet::create([
            'company_id' => $companyId,
            'customer_id' => $customer1->id,
            'balance' => 25000000,
            'points' => 350,
            'tier' => 'gold',
        ]);

        // E. Banks & Bank Accounts
        $bankMellat = Bank::firstOrCreate(['name' => 'بانک ملت', 'company_id' => $companyId]);
        $bankMelli = Bank::firstOrCreate(['name' => 'بانک ملی ایران', 'company_id' => $companyId]);
        $bankAccount = BankAccount::create([
            'company_id' => $companyId,
            'bank_id' => $bankMellat->id,
            'number' => '4982736451',
            'type' => \App\Enums\BankAccountType::CURRENT->value,
            'iban' => 'IR820120000000004982736451',
            'bank_branch' => 'شعبه مرکزی بازار',
            'name' => 'حساب جاری اصلی ملت - بازار',
        ]);

        // F. Cheques & Chequebooks
        $chequebook = Chequebook::create([
            'company_id' => $companyId,
            'bank_account_id' => $bankAccount->id,
            'serial_prefix' => 'CHK',
            'first_leaf' => 1001,
            'last_leaf' => 1050,
            'next_leaf' => 1002,
        ]);

        $cheque = Cheque::create([
            'company_id' => $companyId,
            'direction' => \App\Enums\ChequeType::RECEIVABLE->value,
            'purpose' => \App\Enums\ChequeType::SETTLEMENT->value,
            'amount' => 180000000,
            'write_date' => Carbon::now()->subDays(5)->format('Y-m-d'),
            'due_date' => Carbon::now()->addDays(20)->format('Y-m-d'),
            'cheque_number' => '1001',
            'sayad_number' => '1234567890123456',
            'status' => \App\Enums\ChequeType::REGISTERED->value,
            'customer_id' => $customer1->id,
        ]);

        // G. Accounting Documents
        $document = Document::create([
            'company_id' => $companyId,
            'fiscal_year' => 1405,
            'number' => 1,
            'date' => Carbon::now()->format('Y-m-d'),
            'description' => 'سند افتتاحیه سال مالی ۱۴۰۵ و ثبت موجودی اولیه',
            'status' => 'draft',
        ]);

        // H. Invoices
        $invoice = Invoice::create([
            'company_id' => $companyId,
            'invoice_type' => InvoiceType::SELL->value,
            'customer_id' => $customer1->id,
            'warehouse_id' => $mainWarehouse->id,
            'number' => 1001,
            'date' => Carbon::now()->format('Y-m-d'),
            'status' => InvoiceStatus::PENDING->value,
            'amount' => 695000000,
            'vat' => 69500000,
        ]);

        // I. Employees & HR
        $employee = Employee::factory()->create([
            'company_id' => $companyId,
            'first_name' => 'علی',
            'last_name' => 'کمالی',
            'code' => 'EMP-101',
        ]);

        // J. Capillary Distribution
        $salesRoute = SalesRoute::create([
            'company_id' => $companyId,
            'title' => 'مسیر بازار و لاله زار تهران',
            'code' => 'ROUTE-BAZAAR',
        ]);

        // K. Print Template
        $template = InvoicePrintTemplate::create([
            'company_id' => $companyId,
            'name' => 'قالب فاکتور رسمی مالیاتی A4',
            'paper_size' => 'A4',
            'is_default' => true,
        ]);

        // L. Currency
        $usd = Currency::create([
            'company_id' => $companyId,
            'code' => 'USD',
            'name' => 'دلار آمریکا',
            'symbol' => '$',
            'exchange_rate' => 670000,
        ]);

        // M. Trade Exchange
        $trade = TradeExchange::create([
            'company_id' => $companyId,
            'customer_id' => $customer1->id,
            'partner_name' => 'بازرگانی برادران حسینی',
            'partner_mobile' => '09127778899',
            'status' => 'open',
            'our_total_value' => 695000000,
            'their_total_value' => 670000000,
            'balance_direction' => 'receivable',
            'settlement_status' => 'pending',
            'notes' => 'تهاتر ۵ دستگاه گوشی S24 با تجهیزات جانبی همکار',
        ]);

        return compact(
            'mainWarehouse', 'retailWarehouse', 'phone', 'laptop', 'service',
            'customer1', 'customer2', 'bankAccount', 'cheque', 'chequebook',
            'document', 'invoice', 'employee', 'salesRoute', 'template', 'usd', 'trade'
        );
    }

    /**
     * Exhaustive Audit across ALL routes and pages
     */
    public function test_exhaustive_system_wide_deep_audit(): void
    {
        $entities = $this->seedComprehensiveRealData();
        $company = $this->company;

        $routes = Route::getRoutes();
        $testedRoutes = [];
        $failedEndpoints = [];
        $deadLinksDetected = [];
        $unboundButtons = [];
        $suspiciousPlaceholders = [];
        $untranslatedStrings = [];

        // Ignored non-UI or external / debug / auth routes
        $skippedPatterns = [
            '_debugbar', '_ignition', '_native', '_scramble',
            'login', 'logout', 'register', 'verify', 'forgot-password', 'reset-password',
            'impersonation/leave', 'translations/add', 'translations/delete', 'translations/edit',
            'translations/groups', 'translations/publish', 'translations/locales',
        ];

        foreach ($routes as $route) {
            $methods = $route->methods();
            $uri = $route->uri();

            if (!in_array('GET', $methods)) {
                continue;
            }

            // Skip internal / debug
            $skip = false;
            foreach ($skippedPatterns as $pattern) {
                if (str_contains($uri, $pattern)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            // Resolve route parameters with real model IDs
            $resolvedUri = $uri;
            $hasUnresolvableParam = false;

            $paramReplacements = [
                '{company}' => $company->id,
                '{warehouse}' => $entities['mainWarehouse']->id,
                '{product}' => $entities['phone']->id,
                '{productGroup}' => $entities['phone']->group,
                '{service}' => $entities['service']->id,
                '{serviceGroup}' => $entities['service']->group,
                '{customer}' => $entities['customer1']->id,
                '{customerGroup}' => $entities['customer1']->group,
                '{bankAccount}' => $entities['bankAccount']->id,
                '{bank}' => 1,
                '{cheque}' => $entities['cheque']->id,
                '{chequebook}' => $entities['chequebook']->id,
                '{document}' => $entities['document']->id,
                '{invoice}' => $entities['invoice']->id,
                '{employee}' => $entities['employee']->id,
                '{template}' => $entities['template']->id,
                '{currency}' => $entities['usd']->id,
                '{trade}' => $entities['trade']->id,
                '{type}' => 'receivable',
                '{salesRoute}' => $entities['salesRoute']->id,
                '{tab?}' => 'rates',
                '{user}' => $this->adminUser->id,
                '{role}' => 1,
            ];

            foreach ($paramReplacements as $placeholder => $value) {
                if (str_contains($resolvedUri, $placeholder)) {
                    $resolvedUri = str_replace($placeholder, (string) $value, $resolvedUri);
                }
            }

            // If there's still an unhandled parameter, skip and record
            if (preg_match('/\{[a-zA-Z0-9_?]+\}/', $resolvedUri)) {
                continue;
            }

            $testedRoutes[] = $resolvedUri;

            try {
                $response = $this->actingAs($this->adminUser)
                    ->withSession([
                        'company_id' => $company->id,
                        'fiscal_year' => 1405,
                    ])
                    ->get('/' . ltrim($resolvedUri, '/'));

                $statusCode = $response->getStatusCode();

                if ($statusCode >= 500) {
                    $failedEndpoints[] = [
                        'uri' => $uri,
                        'resolved' => $resolvedUri,
                        'status' => $statusCode,
                        'route_name' => $route->getName(),
                        'error' => '500 Server Error',
                    ];
                    continue;
                }

                // If it returned 200 OK HTML, deep-inspect the page DOM
                if ($statusCode === 200 && str_contains($response->headers->get('Content-Type') ?? '', 'text/html')) {
                    $html = $response->getContent();

                    // 1. Detect dead links: href="#" or href="" or href="javascript:void(0)"
                    preg_match_all('/<a\s+[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER);
                    foreach ($matches as $match) {
                        $href = trim($match[1]);
                        $linkText = strip_tags(trim($match[2]));

                        if ($href === '#' || $href === '' || str_starts_with($href, 'javascript:')) {
                            // Check if it has Alpine @click or x-on:click or alpine attributes
                            $fullAnchor = $match[0];
                            if (!str_contains($fullAnchor, '@click') && !str_contains($fullAnchor, 'x-on:click') && !str_contains($fullAnchor, 'onclick')) {
                                $deadLinksDetected[] = [
                                    'page' => $resolvedUri,
                                    'href' => $href,
                                    'text' => mb_substr($linkText, 0, 40),
                                    'anchor_tag' => mb_substr($fullAnchor, 0, 100),
                                ];
                            }
                        }
                    }

                    // 2. Detect unbound buttons: <button> with no type, no form, no alpine/onclick
                    preg_match_all('/<button\s+([^>]*)>(.*?)<\/button>/is', $html, $btnMatches, PREG_SET_ORDER);
                    foreach ($btnMatches as $btnMatch) {
                        $attrs = $btnMatch[1];
                        $btnText = strip_tags(trim($btnMatch[2]));

                        $hasAction = str_contains($attrs, 'type="submit"') ||
                                     str_contains($attrs, 'type=\'submit\'') ||
                                     str_contains($attrs, '@click') ||
                                     str_contains($attrs, 'x-on:click') ||
                                     str_contains($attrs, 'onclick') ||
                                     str_contains($attrs, 'form=') ||
                                     str_contains($attrs, 'wire:click');

                        if (!$hasAction && !empty($btnText)) {
                            $unboundButtons[] = [
                                'page' => $resolvedUri,
                                'text' => mb_substr($btnText, 0, 40),
                                'tag' => mb_substr($btnMatch[0], 0, 100),
                            ];
                        }
                    }

                    // 3. Detect placeholders or unfinished notes in the HTML
                    $suspiciousPhrases = ['TODO', 'در دست ساخت', 'به زودی', 'mock', 'fake_data', 'در حال توسعه'];
                    foreach ($suspiciousPhrases as $phrase) {
                        if (mb_stripos($html, $phrase) !== false) {
                            $suspiciousPlaceholders[] = [
                                'page' => $resolvedUri,
                                'phrase' => $phrase,
                            ];
                        }
                    }

                    // 4. Detect raw unparsed Blade strings like {{ $
                    if (str_contains($html, '{{ $') || str_contains($html, '{{$')) {
                        $suspiciousPlaceholders[] = [
                            'page' => $resolvedUri,
                            'phrase' => 'Unparsed blade expression: {{ $',
                        ];
                    }
                }
            } catch (\Throwable $e) {
                $failedEndpoints[] = [
                    'uri' => $uri,
                    'resolved' => $resolvedUri,
                    'status' => 'EXCEPTION',
                    'route_name' => $route->getName(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Save detailed results to a JSON file in storage for the audit report generator
        $reportData = [
            'total_get_routes_audited' => count($testedRoutes),
            'tested_routes' => $testedRoutes,
            'failed_endpoints' => $failedEndpoints,
            'dead_links_count' => count($deadLinksDetected),
            'dead_links' => array_slice($deadLinksDetected, 0, 50),
            'unbound_buttons_count' => count($unboundButtons),
            'unbound_buttons' => array_slice($unboundButtons, 0, 50),
            'suspicious_placeholders_count' => count($suspiciousPlaceholders),
            'suspicious_placeholders' => $suspiciousPlaceholders,
        ];

        file_put_contents(storage_path('app/audit_deep_scan_results.json'), json_encode($reportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo "\n======================================================\n";
        echo "DEEP AUDIT SCAN COMPLETE:\n";
        echo "Total GET routes audited: " . count($testedRoutes) . "\n";
        echo "Failed endpoints (500 or Exception): " . count($failedEndpoints) . "\n";
        echo "Dead links (href=# or empty with no JS): " . count($deadLinksDetected) . "\n";
        echo "Unbound buttons: " . count($unboundButtons) . "\n";
        echo "Placeholders / Incomplete markers: " . count($suspiciousPlaceholders) . "\n";
        echo "======================================================\n";

        $this->assertEmpty($failedEndpoints, "Found server errors on endpoints: " . json_encode($failedEndpoints, JSON_UNESCAPED_UNICODE));
    }
}
