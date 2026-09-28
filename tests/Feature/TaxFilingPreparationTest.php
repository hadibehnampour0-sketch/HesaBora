<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\SeasonalTaxReportService;
use App\Services\TaxReadinessService;
use App\Services\TaxRuleService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/** T-8/T-9: the guided "prepare my filing" checklist, built entirely from already-tested pieces. */
class TaxFilingPreparationTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9600;

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

    public function test_the_page_needs_its_own_permission(): void
    {
        $this->get(route('tax-center.prepare'))->assertForbidden();
    }

    public function test_a_period_with_no_readiness_score_calculated_yet_prompts_for_one(): void
    {
        $this->grant('tax-center.prepare', 'companies.index');

        $this->get(route('tax-center.prepare'))
            ->assertOk()
            ->assertSee(__('Not ready yet'))
            ->assertSee(__('Calculate the readiness score first.'));
    }

    public function test_an_unsent_invoice_in_the_period_blocks_readiness_and_links_to_the_queue(): void
    {
        $this->grant('tax-center.prepare', 'invoices.moadian-queue.index');
        app(TaxReadinessService::class)->calculateScore($this->companyId);

        $service = app(SeasonalTaxReportService::class);
        [$year] = gregorian_to_jalali(now()->year, now()->month, now()->day);
        [$start] = $service->quarterDateRange($year, 1);

        $product = $this->createProduct();
        $this->buy([array_merge($this->productItem($product, 10, 100), ['vat' => 0])], true, ++$this->nextInvoiceNumber, $start);
        $this->sell([array_merge($this->productItem($product, 1, 1000), ['vat' => 10])], true, ++$this->nextInvoiceNumber, $start);

        $response = $this->get(route('tax-center.prepare', ['jalali_year' => $year, 'quarter' => 1]));

        $response->assertOk()
            ->assertSee(__('Not ready yet'))
            ->assertSee('href="'.route('invoices.moadian-queue.index').'"', false);
    }

    public function test_everything_clean_and_sent_is_marked_ready(): void
    {
        $this->grant('tax-center.prepare');
        app(TaxRuleService::class)->recordRule(null, 10.0, false, '2020-01-01', null, 'General');
        Company::find($this->companyId)->update([
            'economical_code' => '123', 'national_code' => '456',
            'moadian_username' => 'A1', 'tax_id' => 'T1',
            'certificate_path' => 'moadian/cert.pem', 'private_key_path' => 'moadian/key.pem',
        ]);
        Storage::disk('local')->put('moadian/cert.pem', 'x');
        Storage::disk('local')->put('moadian/key.pem', 'x');
        app(TaxReadinessService::class)->calculateScore($this->companyId);

        $service = app(SeasonalTaxReportService::class);
        [$year] = gregorian_to_jalali(now()->year, now()->month, now()->day);

        // No invoices in this (otherwise empty) quarter -> nothing pending, ledger trivially balances.
        $response = $this->get(route('tax-center.prepare', ['jalali_year' => $year, 'quarter' => 1]));

        $response->assertOk()->assertSee(__('Ready for the tax office'));
    }

    public function test_another_companys_readiness_log_never_leaks_in(): void
    {
        $this->grant('tax-center.prepare');

        $other = Company::factory()->create(['fiscal_year' => 1405]);
        config(['active-company-id' => $other->id]);
        app(TaxReadinessService::class)->calculateScore($other->id);
        config(['active-company-id' => $this->companyId]);

        [$year] = gregorian_to_jalali(now()->year, now()->month, now()->day);
        $this->get(route('tax-center.prepare', ['jalali_year' => $year, 'quarter' => 1]))
            ->assertOk()
            ->assertSee(__('Calculate the readiness score first.'));
    }
}
