<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\ProductGroup;
use App\Models\User;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * Phase U: the ancillary-cost (expense) wizard is a pure presentation layer over the same
 * form fields/route/validation, shown only on the create page in Simple mode.
 */
class AncillaryCostWizardTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 6600;

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
        foreach (['invoices.ancillary-costs.create', 'invoices.ancillary-costs.store', 'invoices.ancillary-costs.edit'] as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
        $this->actingAs($this->user);
    }

    private function buyInvoice()
    {
        $product = $this->createProduct();

        return $this->buy([$this->productItem($product, 5, 100)], true, ++$this->nextInvoiceNumber)['invoice'];
    }

    public function test_advanced_mode_keeps_the_single_page_form(): void
    {
        $invoice = $this->buyInvoice();

        $this->get(route('invoices.ancillary-costs.create', $invoice))
            ->assertOk()
            ->assertDontSee('wizardMode: true', false)
            ->assertDontSee(__('Supplier & cost info'));
    }

    public function test_simple_mode_shows_the_two_step_wizard(): void
    {
        $this->user->update(['ui_mode' => 'simple']);
        $invoice = $this->buyInvoice();

        $this->get(route('invoices.ancillary-costs.create', $invoice))
            ->assertOk()
            ->assertSee('wizardMode: true', false)
            ->assertSee('step === 1', false)
            ->assertSee('step === 2', false)
            ->assertSee(__('Supplier & cost info'))
            ->assertSee(__('Items & submit'));
    }
}
