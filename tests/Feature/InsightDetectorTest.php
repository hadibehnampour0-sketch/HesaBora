<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\Industry\IndustrySettingsService;
use App\Services\InsightDetectorService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * Phase AI tier 1: deterministic, permission-gated, read-only insights.
 */
class InsightDetectorTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 5500;

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

    private function types(): array
    {
        return array_column(app(InsightDetectorService::class)->detect($this->user), 'type');
    }

    public function test_no_permissions_means_no_insights_even_when_data_would_trigger_them(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 50, 100)], true, ++$this->nextInvoiceNumber);
        $this->sell([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber);
        $this->sell([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber);

        $this->assertSame([], $this->types());
    }

    public function test_two_identical_invoices_a_day_apart_are_flagged_as_possible_duplicates(): void
    {
        $this->grant('invoices.index');
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 50, 100)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(10)->toDateString());
        $this->sell([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDay()->toDateString());
        $this->sell([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber, Carbon::today()->toDateString());

        $this->assertContains('possible_duplicate_invoice', $this->types());
    }

    public function test_identical_invoices_far_apart_or_with_different_amounts_are_not_duplicates(): void
    {
        $this->grant('invoices.index');
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 50, 100)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(20)->toDateString());
        $this->sell([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(10)->toDateString());
        $this->sell([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber, Carbon::today()->toDateString());
        $this->sell([$this->productItem($product, 1, 501)], true, ++$this->nextInvoiceNumber, Carbon::today()->toDateString());

        $this->assertNotContains('possible_duplicate_invoice', $this->types());
    }

    public function test_a_recent_buy_price_far_above_the_trailing_average_is_flagged(): void
    {
        $this->grant('invoices.index');
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(80)->toDateString());
        $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(60)->toDateString());
        $this->buy([$this->productItem($product, 10, 140)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(2)->toDateString());

        $this->assertContains('buy_price_spike', $this->types());
    }

    public function test_a_modest_price_change_or_thin_history_is_not_flagged(): void
    {
        $this->grant('invoices.index');
        $product = $this->createProduct();
        // Only one baseline purchase -> not enough history.
        $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(60)->toDateString());
        $this->buy([$this->productItem($product, 10, 300)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(2)->toDateString());
        $this->assertNotContains('buy_price_spike', $this->types());

        // Enough history but only a 10% rise.
        $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(70)->toDateString());
        $this->buy([$this->productItem($product, 10, 110)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDay()->toDateString());
        $this->assertNotContains('buy_price_spike', $this->types());
    }

    public function test_receivable_concentration_needs_the_ageing_permission(): void
    {
        $product = $this->createProduct();
        $groupId = $this->customer->group_id;
        $second = Customer::factory()->withGroup(CustomerGroup::find($groupId))->withSubject()->create(['company_id' => $this->companyId]);
        $third = Customer::factory()->withGroup(CustomerGroup::find($groupId))->withSubject()->create(['company_id' => $this->companyId]);

        $this->buy([$this->productItem($product, 100, 10)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(5)->toDateString());
        $this->sell([$this->productItem($product, 1, 8000)], true, ++$this->nextInvoiceNumber, Carbon::today()->toDateString());
        $this->customer = $second;
        $this->sell([$this->productItem($product, 1, 1000)], true, ++$this->nextInvoiceNumber, Carbon::today()->toDateString());
        $this->customer = $third;
        $this->sell([$this->productItem($product, 1, 1000)], true, ++$this->nextInvoiceNumber, Carbon::today()->toDateString());

        $this->assertNotContains('receivable_concentration', $this->types());

        $this->grant('ageing.receivables');
        $this->assertContains('receivable_concentration', $this->types());
    }

    public function test_insights_are_ordered_by_severity_and_reach_the_today_endpoint(): void
    {
        $this->grant('home.today', 'invoices.index');
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 50, 100)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(10)->toDateString());
        $this->sell([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDay()->toDateString());
        $this->sell([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber, Carbon::today()->toDateString());

        $blocks = $this->getJson(route('home.today'))->assertOk()->json('blocks');

        $this->assertArrayHasKey('insights', $blocks);
        $this->assertSame('possible_duplicate_invoice', $blocks['insights'][0]['type']);
        $this->assertNotEmpty($blocks['insights'][0]['url']);
    }

    public function test_another_companys_invoices_never_produce_insights(): void
    {
        $this->grant('invoices.index');
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 50, 100)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(10)->toDateString());
        $this->sell([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDay()->toDateString());
        $this->sell([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber, Carbon::today()->toDateString());
        $this->assertContains('possible_duplicate_invoice', $this->types()); // positive control

        $other = Company::factory()->create();
        config(['active-company-id' => $other->id]);

        $this->assertSame([], $this->types());
    }

    public function test_a_detector_can_be_switched_off_per_company_without_code(): void
    {
        $this->grant('invoices.index');
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 50, 100)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(10)->toDateString());
        $this->sell([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDay()->toDateString());
        $this->sell([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber, Carbon::today()->toDateString());
        $this->assertContains('possible_duplicate_invoice', $this->types());

        app(IndustrySettingsService::class)->update('business_insights', ['detect_duplicates' => '0'], $this->user->id);

        $this->assertNotContains('possible_duplicate_invoice', $this->types());
    }

    public function test_thresholds_come_from_company_settings(): void
    {
        $this->grant('invoices.index');
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(80)->toDateString());
        $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(60)->toDateString());
        $this->buy([$this->productItem($product, 10, 130)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(2)->toDateString());

        $this->assertContains('buy_price_spike', $this->types()); // 30% >= default 25%

        app(IndustrySettingsService::class)->update('business_insights', ['price_spike_percent' => '50'], $this->user->id);

        $this->assertNotContains('buy_price_spike', $this->types());
    }
}
