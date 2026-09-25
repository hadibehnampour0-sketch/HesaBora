<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\PlainReportService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * Phase U: plain-language business summary, read-only and permission-gated per block.
 */
class PlainReportTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 3300;

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
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['name' => 'Ali Trading', 'company_id' => $this->companyId]);

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

    public function test_the_page_needs_its_permission(): void
    {
        $this->get(route('reports.plain'))->assertForbidden();

        $this->grant('reports.plain');
        $this->get(route('reports.plain'))->assertOk()->assertSee(__('How is my business doing?'));
    }

    public function test_period_ranges_follow_the_jalali_calendar(): void
    {
        $service = app(PlainReportService::class);
        $today = Carbon::parse(jalali_to_gregorian(1405, 7, 3, '/')); // 3 Mehr 1405

        [$from, $to] = $service->periodRange('this_month', $today);
        $this->assertSame(jalali_to_gregorian(1405, 7, 1, '/'), $from->format('Y/m/d'));
        $this->assertSame(jalali_to_gregorian(1405, 7, 30, '/'), $to->format('Y/m/d')); // Mehr has 30 days

        [$from, $to] = $service->periodRange('last_month', $today);
        $this->assertSame(jalali_to_gregorian(1405, 6, 1, '/'), $from->format('Y/m/d'));
        $this->assertSame(jalali_to_gregorian(1405, 6, 31, '/'), $to->format('Y/m/d')); // Shahrivar has 31 days

        // January-of-the-year edge: last month from Farvardin is the previous year's Esfand.
        [$from] = $service->periodRange('last_month', Carbon::parse(jalali_to_gregorian(1405, 1, 5, '/')));
        [$jy, $jm] = gregorian_to_jalali($from->year, $from->month, $from->day);
        $this->assertSame([1404, 12], [$jy, $jm]);
    }

    public function test_sales_and_purchases_are_summed_for_the_selected_period_only(): void
    {
        $product = $this->createProduct();
        $today = Carbon::today();

        $this->buy([$this->productItem($product, 50, 100)], true, ++$this->nextInvoiceNumber, $today->toDateString());
        $this->sell([$this->productItem($product, 2, 1000)], true, ++$this->nextInvoiceNumber, $today->toDateString());
        $this->sell([$this->productItem($product, 1, 400)], true, ++$this->nextInvoiceNumber, $today->copy()->subDays(70)->toDateString());

        $this->grant('invoices.index');
        $trade = app(PlainReportService::class)->build($this->user, 'this_month', $today)['blocks']['trade'];

        $this->assertSame(1, $trade['sell']['count']);
        $this->assertSame(2000.0, $trade['sell']['amount']);
        $this->assertSame(5000.0, $trade['buy']['amount']);
        $this->assertSame(-3000.0, $trade['difference']);
        $this->assertSame('Ali Trading', $trade['top_customers'][0]['name']);
        $this->assertSame(2000.0, $trade['top_products'][0]['amount']);
    }

    public function test_blocks_appear_only_with_their_underlying_permission(): void
    {
        $this->grant('reports.plain');
        $this->assertSame([], app(PlainReportService::class)->build($this->user)['blocks']);

        $this->grant('invoices.index', 'ageing.receivables');
        $blocks = app(PlainReportService::class)->build($this->user)['blocks'];

        $this->assertArrayHasKey('trade', $blocks);
        $this->assertArrayHasKey('owed_to_me', $blocks);
        $this->assertArrayNotHasKey('i_owe', $blocks);
        $this->assertArrayNotHasKey('books', $blocks);
    }

    public function test_an_unknown_period_falls_back_to_this_month(): void
    {
        $this->grant('reports.plain');

        $this->get(route('reports.plain', ['period' => 'whenever']))->assertOk();
        $this->assertSame('this_month', app(PlainReportService::class)->build($this->user, 'whenever')['period']);
    }

    public function test_another_companys_invoices_never_appear(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 50, 100)], true, ++$this->nextInvoiceNumber, Carbon::today()->toDateString());
        $this->sell([$this->productItem($product, 2, 1000)], true, ++$this->nextInvoiceNumber, Carbon::today()->toDateString());
        $this->grant('invoices.index');
        $this->assertSame(1, app(PlainReportService::class)->build($this->user)['blocks']['trade']['sell']['count']); // positive control

        $other = Company::factory()->create();
        config(['active-company-id' => $other->id]);

        $this->assertSame(0, app(PlainReportService::class)->build($this->user)['blocks']['trade']['sell']['count']);
    }
}
