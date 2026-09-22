<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Regression coverage for the product/service auto-code generation bug: the
 * previous implementation used MySQL-only SQL (`REGEXP`, `CAST(... AS
 * UNSIGNED)`), which threw a QueryException on SQLite (used by the desktop
 * app and the default test suite) whenever a product/service was created
 * without an explicit code.
 */
class ProductServiceCodeGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');

        $company = Company::factory()->create();
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $company->users()->syncWithoutDetaching($this->user);

        $this->user->givePermissionTo([
            Permission::firstOrCreate(['name' => 'products.index']),
            Permission::firstOrCreate(['name' => 'products.create']),
            Permission::firstOrCreate(['name' => 'products.store']),
            Permission::firstOrCreate(['name' => 'services.index']),
            Permission::firstOrCreate(['name' => 'services.create']),
            Permission::firstOrCreate(['name' => 'services.store']),
        ]);

        $this->withCookies(['active-company-id' => $this->companyId]);
        config(['active-company-id' => $this->companyId]);

        $this->actingAs($this->user);
    }

    public function test_creating_a_product_without_a_code_does_not_crash_and_assigns_the_next_number(): void
    {
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId]);

        $response = $this->post(route('products.store'), [
            'name' => 'First Product',
            'group' => $group->id,
            'oversell' => 0,
        ]);

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', ['name' => 'First Product', 'code' => '1']);

        $response = $this->post(route('products.store'), [
            'name' => 'Second Product',
            'group' => $group->id,
            'oversell' => 0,
        ]);

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', ['name' => 'Second Product', 'code' => '2']);
    }

    public function test_creating_a_service_without_a_code_does_not_crash_and_assigns_the_next_number(): void
    {
        $group = ServiceGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);

        $response = $this->post(route('services.store'), [
            'name' => 'First Service',
            'group' => $group->id,
        ]);

        $response->assertRedirect(route('services.index'));
        $this->assertDatabaseHas('services', ['name' => 'First Service', 'code' => '1']);

        $response = $this->post(route('services.store'), [
            'name' => 'Second Service',
            'group' => $group->id,
        ]);

        $response->assertRedirect(route('services.index'));
        $this->assertDatabaseHas('services', ['name' => 'Second Service', 'code' => '2']);
    }
}
