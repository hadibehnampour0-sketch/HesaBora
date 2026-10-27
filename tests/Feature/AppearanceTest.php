<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
        $this->user->companies()->attach($this->company->id);

        $this->actingAs($this->user);
        session(['company_id' => $this->company->id]);
    }

    public function test_appearance_settings_page_renders_successfully(): void
    {
        $response = $this->get(route('settings.appearance'));

        $response->assertOk();
        $response->assertSee('Google Flow');
        $response->assertSee('Linear Obsidian');
        $response->assertSee('Stripe Glass');
        $response->assertSee('فیروزه‌ای');
        $response->assertSee('زمردی');
        $response->assertSee('کاربنی');
        $response->assertSee('بنفش');
        $response->assertSee('تیتانیوم');
        $response->assertSee('حالت روز');
        $response->assertSee('حالت شب');
        $response->assertSee('فشرده حسابداری');
        $response->assertSee('استاندارد و جادار');
    }

    public function test_user_can_update_theme_preferences(): void
    {
        $response = $this->post(route('settings.appearance.update'), [
            'ui_theme' => 'linear',
            'accent_color' => 'indigo',
            'ui_density' => 'compact',
            'theme_mode' => 'dark',
        ]);

        $response->assertRedirect(route('settings.appearance'));
        $response->assertCookie('dakhl_ui_theme', 'linear');
        $response->assertCookie('dakhl_accent_color', 'indigo');
        $response->assertCookie('dakhl_ui_density', 'compact');
        $response->assertCookie('dakhl_theme_mode', 'dark');
        $response->assertSessionHas('dakhl_theme_mode', 'dark');
    }

    public function test_theme_update_rejects_invalid_theme(): void
    {
        $response = $this->post(route('settings.appearance.update'), [
            'ui_theme' => 'invalid_theme',
        ]);

        $response->assertSessionHasErrors('ui_theme');
    }

    public function test_theme_update_rejects_invalid_accent_and_density(): void
    {
        $response = $this->post(route('settings.appearance.update'), [
            'ui_theme' => 'flow',
            'accent_color' => 'neon_pink',
            'ui_density' => 'ultra_wide',
            'theme_mode' => 'cyberpunk',
        ]);

        $response->assertSessionHasErrors(['accent_color', 'ui_density', 'theme_mode']);
    }

    public function test_bank_reconciliation_landing_redirects_to_bank_accounts_when_none_exist(): void
    {
        Permission::findOrCreate('bank-reconciliation.*', 'web');
        $this->user->givePermissionTo('bank-reconciliation.*');

        $response = $this->get(route('bank-reconciliation.landing'));

        $response->assertRedirect(route('bank-accounts.index'));
    }
}
