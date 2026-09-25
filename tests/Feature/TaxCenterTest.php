<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/** The Tax Center is a navigation hub: cards appear only for screens the user may open. */
class TaxCenterTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create(['fiscal_year' => 1405]);
        $this->user = User::factory()->create();
        $company->users()->syncWithoutDetaching($this->user);
        $this->withCookies(['active-company-id' => (string) $company->id]);
        $_COOKIE['active-company-id'] = (string) $company->id;
        config(['active-company-id' => $company->id, 'active-company-fiscal-year' => 1405]);
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
        $this->get(route('tax-center.index'))->assertForbidden();
    }

    public function test_only_permitted_areas_are_linked(): void
    {
        $this->grant('access-super-admin-panel', 'tax-center.index', 'seasonal-tax.index');

        $this->get(route('tax-center.index'))
            ->assertOk()
            ->assertSee(route('seasonal-tax.index'), false)
            ->assertDontSee(route('tax-alerts.index'), false)
            ->assertDontSee(route('invoices.moadian-histories.index'), false);
    }
}
