<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Phase U: Simple mode swaps the sidebar for a short plain-language menu over the SAME
 * routes and permissions (a "show all features" toggle keeps the full menu reachable).
 */
class SimpleSidebarTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        config(['active-company-id' => $company->id]);
        $this->user = User::factory()->create();
        $company->users()->syncWithoutDetaching($this->user);

        foreach (['invoices.create', 'invoices.index', 'customers.create', 'ageing.receivables'] as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }

        $this->actingAs($this->user)->withCookies(['active-company-id' => (string) $company->id]);
    }

    public function test_advanced_mode_keeps_the_full_menu_without_the_toggle(): void
    {
        $this->get(route('account.edit'))
            ->assertOk()
            ->assertDontSee(__('Show all features'))
            ->assertDontSee(__('Who owes me'));
    }

    public function test_simple_mode_shows_the_short_menu_with_the_toggle(): void
    {
        $this->user->update(['ui_mode' => 'simple']);

        $this->get(route('account.edit'))
            ->assertOk()
            ->assertSee(__('Show all features'))
            ->assertSee(__('New sale'))
            ->assertSee(__('Who owes me'));
    }

    public function test_the_simple_menu_only_lists_what_the_user_may_open(): void
    {
        $this->user->update(['ui_mode' => 'simple']);

        $html = $this->get(route('account.edit'))->assertOk()->getContent();

        $this->assertStringNotContainsString(__('Whom I owe'), $html);     // no ageing.payables
        $this->assertStringNotContainsString(__('Product list'), $html);   // no products.index
        $this->assertStringContainsString(route('customers.create'), $html);
    }
}
