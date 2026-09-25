<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\TodayDashboardService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * Phase U "Today" dashboard: a permission-gated read view over existing services.
 */
class TodayDashboardTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 7700;

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

    private function grant(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
    }

    public function test_the_endpoint_requires_the_home_today_permission(): void
    {
        $this->getJson(route('home.today'))->assertForbidden();
    }

    public function test_a_user_with_no_report_permissions_gets_no_blocks(): void
    {
        $this->grant('home.today');

        $this->getJson(route('home.today'))->assertOk()->assertJsonPath('blocks', []);
    }

    public function test_blocks_are_gated_by_the_permission_of_the_full_report(): void
    {
        $this->grant('home.today', 'invoices.index');

        $blocks = $this->getJson(route('home.today'))->assertOk()->json('blocks');

        $this->assertArrayHasKey('sales', $blocks);
        $this->assertArrayNotHasKey('receivables', $blocks);
        $this->assertArrayNotHasKey('payables', $blocks);
        $this->assertArrayNotHasKey('cheques', $blocks);

        $this->grant('ageing.receivables', 'cheques.index');
        $blocks = $this->getJson(route('home.today'))->json('blocks');

        $this->assertArrayHasKey('receivables', $blocks);
        $this->assertArrayHasKey('cheques', $blocks);
        $this->assertArrayNotHasKey('payables', $blocks);
    }

    public function test_todays_sales_and_purchases_are_totalled_and_the_series_covers_seven_days(): void
    {
        $product = $this->createProduct();
        $today = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        $this->buy([$this->productItem($product, 20, 100)], true, ++$this->nextInvoiceNumber, $today);
        $this->sell([$this->productItem($product, 2, 1000)], true, ++$this->nextInvoiceNumber, $today);
        $this->sell([$this->productItem($product, 1, 400)], true, ++$this->nextInvoiceNumber, $yesterday);

        $this->grant('invoices.index');
        $sales = app(TodayDashboardService::class)->build($this->user)['blocks']['sales'];

        $this->assertSame(1, $sales['today_sell']['count']);
        $this->assertSame(2000.0, $sales['today_sell']['amount']);
        $this->assertSame(1, $sales['today_buy']['count']);
        $this->assertSame(2000.0, $sales['today_buy']['amount']);
        $this->assertCount(7, $sales['series']);

        $byDate = collect($sales['series'])->keyBy('date');
        $this->assertSame(2000.0, $byDate[$today]['sell']);
        $this->assertSame(400.0, $byDate[$yesterday]['sell']);
    }

    public function test_unapproved_invoices_are_not_counted(): void
    {
        $product = $this->createProduct();
        $today = Carbon::today()->toDateString();

        $this->buy([$this->productItem($product, 20, 100)], true, ++$this->nextInvoiceNumber, $today);
        $this->sell([$this->productItem($product, 2, 1000)], false, ++$this->nextInvoiceNumber, $today);

        $this->grant('invoices.index');
        $sales = app(TodayDashboardService::class)->build($this->user)['blocks']['sales'];

        $this->assertSame(0, $sales['today_sell']['count']);
        $this->assertSame(0.0, $sales['today_sell']['amount']);
    }

    public function test_receivables_split_into_current_and_overdue_with_top_debtors(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 50, 100)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(100)->toDateString());
        $this->sell([$this->productItem($product, 1, 700)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(60)->toDateString());
        $this->sell([$this->productItem($product, 1, 300)], true, ++$this->nextInvoiceNumber, Carbon::today()->toDateString());

        $this->grant('ageing.receivables');
        $block = app(TodayDashboardService::class)->build($this->user)['blocks']['receivables'];

        $this->assertSame(1000.0, $block['total']);
        $this->assertSame(300.0, $block['current']);
        $this->assertSame(700.0, $block['overdue']);
        $this->assertCount(1, $block['top_overdue']);
        $this->assertSame(700.0, $block['top_overdue'][0]['remaining']);
        $this->assertSame(60, $block['top_overdue'][0]['oldest_days']);
    }

    public function test_another_companys_invoices_never_appear(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 20, 100)], true, ++$this->nextInvoiceNumber, Carbon::today()->toDateString());
        $this->sell([$this->productItem($product, 2, 1000)], true, ++$this->nextInvoiceNumber, Carbon::today()->toDateString());

        $other = Company::factory()->create();
        config(['active-company-id' => $other->id]);

        $this->grant('invoices.index');
        $sales = app(TodayDashboardService::class)->build($this->user)['blocks']['sales'];

        // Positive control (below) proves the data exists for the original company.
        $this->assertSame(0, $sales['today_sell']['count']);

        config(['active-company-id' => $this->companyId]);
        $this->assertSame(1, app(TodayDashboardService::class)->build($this->user)['blocks']['sales']['today_sell']['count']);
    }

    public function test_quick_start_lists_only_steps_the_user_may_perform_and_disappears_when_all_are_done(): void
    {
        $this->grant('home.today', 'customers.create', 'products.create', 'invoices.create');

        $steps = $this->getJson(route('home.today'))->assertOk()->json('blocks.quick_start');
        $byKey = collect($steps)->keyBy('key');

        // The setUp customer already exists; no product and no sale yet.
        $this->assertTrue($byKey['customer']['done']);
        $this->assertFalse($byKey['product']['done']);
        $this->assertFalse($byKey['sale']['done']);

        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber);
        $this->sell([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber);

        $blocks = $this->getJson(route('home.today'))->json('blocks');
        $this->assertArrayNotHasKey('quick_start', $blocks);
    }

    public function test_quick_start_is_hidden_from_users_who_can_create_nothing(): void
    {
        $this->grant('home.today');

        $this->getJson(route('home.today'))->assertOk()->assertJsonPath('blocks', []);
    }
}
