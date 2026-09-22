<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
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
 * P8.2 (Zero-Knowledge UX). The wizard is a PURE presentation change --
 * same fields, same route, same validation, same InvoiceService call --
 * scoped to a brand new Sell invoice when the user is in Simple mode.
 * Editing and every other invoice type are completely unaffected
 * regardless of mode (see create.blade.php's own `$isSellWizard` guard).
 */
class SellInvoiceWizardTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 8100;

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
        foreach (['invoices.create', 'invoices.store', 'invoices.edit'] as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
        $this->actingAs($this->user);
    }

    public function test_advanced_mode_users_see_the_unchanged_single_page_form(): void
    {
        $response = $this->get(route('invoices.create', ['invoice_type' => 'sell']));

        $response->assertOk();
        $response->assertDontSee('wizardMode: true', false);
        $response->assertDontSee(__('Buyer & invoice info'));
    }

    public function test_simple_mode_users_see_the_guided_wizard_for_a_new_sell_invoice(): void
    {
        $this->user->update(['ui_mode' => 'simple']);

        $response = $this->get(route('invoices.create', ['invoice_type' => 'sell']));

        $response->assertOk();
        $response->assertSee('wizardMode: true', false);
        $response->assertSee(__('Buyer & invoice info'));
        $response->assertSee(__('Items & discounts'));
        $response->assertSee(__('Description & submit'));
    }

    public function test_simple_mode_does_not_affect_other_invoice_types(): void
    {
        $this->user->update(['ui_mode' => 'simple']);

        $response = $this->get(route('invoices.create', ['invoice_type' => 'buy']));

        $response->assertOk();
        $response->assertDontSee('wizardMode: true', false);
    }

    public function test_simple_mode_does_not_affect_editing_an_existing_invoice(): void
    {
        $this->user->update(['ui_mode' => 'simple']);
        $product = $this->createProduct();
        $invoice = $this->sell([$this->productItem($product, 1, 100)], false, ++$this->nextInvoiceNumber)['invoice'];

        $response = $this->get(route('invoices.edit', $invoice));

        $response->assertOk();
        $response->assertDontSee('wizardMode: true', false);
    }

    public function test_submitting_the_wizard_form_creates_the_invoice_exactly_like_the_advanced_form(): void
    {
        $this->user->update(['ui_mode' => 'simple']);
        $product = $this->createProduct();
        $warehouseId = $product->warehouseStocks()->orderBy('warehouse_id')->value('warehouse_id');

        $response = $this->post(route('invoices.store'), [
            'title' => 'Sell invoice',
            'date' => convertToJalali(now()->toDateString(), true),
            'invoice_type' => InvoiceType::SELL->valueName(),
            'customer_id' => $this->customer->id,
            'warehouse_id' => $warehouseId,
            'document_number' => ++$this->nextInvoiceNumber,
            'invoice_number' => $this->nextInvoiceNumber,
            'transactions' => [[
                'item_id' => 'product-'.$product->id,
                'quantity' => 1,
                'unit' => 100,
                'total' => 100,
                'off' => 0,
                'vat' => 0,
            ]],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('invoices', ['title' => 'Sell invoice']);
    }
}
