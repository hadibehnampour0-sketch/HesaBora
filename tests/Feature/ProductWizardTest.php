<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * Phase U: the New Product wizard is a pure presentation layer over the same form
 * fields/route/validation, shown only on `products.create` in Simple mode.
 */
class ProductWizardTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    protected User $user;

    protected int $companyId;

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

        $this->user = User::factory()->create();
        Company::find($this->companyId)->users()->syncWithoutDetaching($this->user);
        foreach (['products.create', 'products.store', 'products.edit', 'products.index'] as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
        $this->actingAs($this->user);
    }

    public function test_advanced_mode_keeps_the_single_page_form(): void
    {
        $this->get(route('products.create'))
            ->assertOk()
            ->assertDontSee('wizardMode: true', false)
            ->assertDontSee(__('Name & group'));
    }

    public function test_simple_mode_shows_the_three_step_wizard(): void
    {
        $this->user->update(['ui_mode' => 'simple']);

        $this->get(route('products.create'))
            ->assertOk()
            ->assertSee('wizardMode: true', false)
            ->assertSee('step === 1', false)
            ->assertSee('step === 2', false)
            ->assertSee('step === 3', false)
            ->assertSee(__('Name & group'))
            ->assertSee(__('Price & stock'))
            ->assertSee(__('More details'));
    }

    public function test_the_wizard_still_contains_every_field_of_the_advanced_form(): void
    {
        $advanced = $this->get(route('products.create'))->getContent();
        $this->user->update(['ui_mode' => 'simple']);
        $simple = $this->get(route('products.create'))->getContent();

        foreach (['group', 'code', 'name', 'sstid', 'selling_price', 'vat', 'notes', 'location', 'discount_formula', 'oversell', 'quantity_warning', 'quantity'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $advanced, "advanced form lost {$field}");
            $this->assertStringContainsString('name="'.$field.'"', $simple, "wizard lost {$field}");
        }
    }

    public function test_the_edit_page_is_never_a_wizard(): void
    {
        $this->user->update(['ui_mode' => 'simple']);
        $group = ProductGroup::first();
        $product = Product::factory()->create(['group' => $group->id, 'company_id' => $this->companyId]);

        $this->get(route('products.edit', $product))
            ->assertOk()
            ->assertDontSee('wizardMode: true', false);
    }
}
