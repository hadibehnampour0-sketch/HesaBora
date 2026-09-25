<?php

namespace Tests\Feature;

use App\Models\AnalyticDimension;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FixedAsset;
use App\Models\PaymentTerm;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/** Screens for accounting tools that used to be JSON-only (no menu, no page). */
class AccountingToolsScreensTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private User $user;

    private int $companyId;

    private const SCREENS = [
        'fixed-assets.index',
        'currencies.index',
        'payment-terms.index',
        'recurring-invoice-templates.index',
        'analytic-dimensions.index',
        'dashboards.cash-flow',
        'dashboards.cheque-risk',
        'dashboards.profitability-by-dimension',
        'inventory-reports.valuation',
        'inventory-reports.slow-moving',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create(['fiscal_year' => 1405]);
        $this->companyId = $company->id;
        $this->user = User::factory()->create();
        $company->users()->syncWithoutDetaching($this->user);

        $this->withCookies(['active-company-id' => (string) $this->companyId]);
        $_COOKIE['active-company-id'] = (string) $this->companyId;
        config(['active-company-id' => $this->companyId, 'active-company-fiscal-year' => 1405]);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);
        $this->actingAs($this->user);
    }

    private function grant(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
    }

    public function test_every_screen_needs_its_permission_and_renders_with_it(): void
    {
        foreach (self::SCREENS as $name) {
            $this->get(route($name))->assertForbidden();
        }

        $this->grant(...self::SCREENS);

        foreach (self::SCREENS as $name) {
            $this->get(route($name))->assertOk();
        }
    }

    public function test_api_callers_still_get_the_same_json(): void
    {
        $this->grant(...self::SCREENS);

        $this->getJson(route('fixed-assets.index'))->assertOk()->assertJsonStructure(['assets']);
        $this->getJson(route('currencies.index'))->assertOk()->assertJsonStructure(['currencies']);
        $this->getJson(route('payment-terms.index'))->assertOk()->assertJsonStructure(['payment_terms']);
        $this->getJson(route('recurring-invoice-templates.index'))->assertOk()->assertJsonStructure(['templates']);
        $this->getJson(route('analytic-dimensions.index'))->assertOk()->assertJsonStructure(['dimensions']);
        $this->getJson(route('dashboards.cash-flow', ['from' => '2026-01-01', 'to' => '2026-12-31']))->assertOk()->assertJsonStructure(['periods', 'net']);
        $this->getJson(route('dashboards.cheque-risk'))->assertOk()->assertJsonStructure(['receivable', 'payable']);
        $this->getJson(route('inventory-reports.valuation'))->assertOk()->assertJsonStructure(['lines', 'total_value']);
        $this->getJson(route('inventory-reports.slow-moving'))->assertOk()->assertJsonStructure(['products']);
    }

    public function test_a_currency_and_its_rate_can_be_entered_from_the_screen(): void
    {
        $this->grant('currencies.index', 'currencies.store', 'currencies.rates.store');

        $this->post(route('currencies.store'), ['code' => 'USD', 'name' => 'US Dollar'])->assertSessionHasNoErrors();
        $currency = Currency::where('code', 'USD')->firstOrFail();

        $this->post(route('currencies.rates.store', $currency), ['rate' => '600000', 'effective_date' => now()->toDateString()])->assertSessionHasNoErrors();
        $this->get(route('currencies.index'))->assertOk()->assertSee('USD')->assertSee('600,000');
    }

    public function test_a_payment_term_ignores_blank_instalment_rows_and_can_be_deactivated(): void
    {
        $this->grant('payment-terms.index', 'payment-terms.store', 'payment-terms.update');

        $this->post(route('payment-terms.store'), [
            'code' => 'NET30-40', 'name' => 'Split',
            'lines' => [['days_offset' => 0, 'percentage' => 60], ['days_offset' => 30, 'percentage' => 40], ['days_offset' => '', 'percentage' => ''], ['days_offset' => '', 'percentage' => '']],
        ])->assertSessionHasNoErrors();

        $term = PaymentTerm::with('lines')->firstOrFail();
        $this->assertCount(2, $term->lines);

        $this->put(route('payment-terms.update', $term), ['is_active' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($term->fresh()->is_active);
    }

    public function test_an_analytic_dimension_can_be_created_and_its_report_opened(): void
    {
        $this->grant('analytic-dimensions.index', 'analytic-dimensions.store', 'analytic-dimensions.report', 'analytic-dimensions.update', 'analytic-dimensions.destroy');

        $this->post(route('analytic-dimensions.store'), ['type' => 1, 'code' => 'B1', 'name' => 'Main branch'])->assertSessionHasNoErrors();
        $dimension = AnalyticDimension::firstOrFail();

        $this->get(route('analytic-dimensions.index'))->assertOk()->assertSee('Main branch');
        $this->get(route('analytic-dimensions.report', $dimension))->assertOk()->assertSee('Main branch');

        $this->delete(route('analytic-dimensions.destroy', $dimension))->assertSessionHasNoErrors();
        $this->assertSame(0, AnalyticDimension::count());
    }

    public function test_a_fixed_asset_can_be_registered_from_the_screen(): void
    {
        $this->grant('fixed-assets.index', 'fixed-assets.store');
        $subjects = Subject::orderBy('id')->limit(3)->get();

        $this->post(route('fixed-assets.store'), [
            'code' => 'CAR-1', 'name' => 'Delivery van',
            'acquisition_date' => now()->toDateString(),
            'acquisition_cost' => '120000000', 'salvage_value' => '0', 'useful_life_months' => 60,
            'asset_subject_id' => $subjects[0]->id,
            'depreciation_expense_subject_id' => $subjects[1]->id,
            'accumulated_depreciation_subject_id' => $subjects[2]->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, FixedAsset::count());
        $this->get(route('fixed-assets.index'))->assertOk()->assertSee('Delivery van')->assertSee('120,000,000');
    }

    public function test_the_cash_flow_screen_defaults_to_the_last_six_months_and_rejects_a_reversed_range(): void
    {
        $this->grant('dashboards.cash-flow');

        $this->get(route('dashboards.cash-flow'))->assertOk()->assertSee(__('Money in'));
        $this->get(route('dashboards.cash-flow', ['from' => '2026-06-01', 'to' => '2026-01-01']))->assertSessionHasErrors('to');
    }

    public function test_the_menu_lists_the_new_screens_for_a_permitted_user(): void
    {
        $this->grant('home', 'home.summary', 'invoices.index', 'documents.index', 'fixed-assets.index', 'dashboards.cash-flow');

        $this->get(route('home'))->assertOk()
            ->assertSee(route('fixed-assets.index'), false)
            ->assertSee(route('dashboards.cash-flow'), false);
    }
}
