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

    public function test_tax_is_a_top_level_menu_entry_separate_from_reports(): void
    {
        $this->grant('home', 'home.summary', 'invoices.index', 'tax-center.index', 'seasonal-tax.index');

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<summary[^>]*>\s*<svg[^>]*>.*?</svg>\s*'.preg_quote(__('Tax'), '#').'\s*</summary>#s', $html);
        // No Reports permission is held, so no Reports dropdown may exist just because tax items are.
        $this->assertDoesNotMatchRegularExpression('#<summary[^>]*>\s*<svg[^>]*>.*?</svg>\s*'.preg_quote(__('Reports'), '#').'\s*</summary>#s', $html);
    }
}
