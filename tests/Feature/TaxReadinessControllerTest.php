<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\TaxRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TaxReadinessControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $company->users()->syncWithoutDetaching($this->user);
        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => (string) $this->companyId]);

        app(TaxRuleService::class)->recordRule(null, 10.0, false, '2020-01-01', null, 'General');
    }

    private function grant(string $permission): void
    {
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
    }

    public function test_a_user_without_permission_cannot_view_the_dashboard(): void
    {
        $this->get(route('tax-readiness.index'))->assertForbidden();
    }

    public function test_the_dashboard_shows_a_prompt_when_no_score_exists_yet(): void
    {
        $this->grant('tax-readiness.index');

        $this->get(route('tax-readiness.index'))
            ->assertOk()
            ->assertSee(__('No score has been calculated yet. Click "Recalculate score" to run the first check.'));
    }

    public function test_recalculating_requires_its_own_permission(): void
    {
        $this->grant('tax-readiness.index');

        $this->post(route('tax-readiness.recalculate'))->assertForbidden();
        $this->assertSame(0, \App\Models\TaxReadinessLog::count());
    }

    public function test_recalculating_creates_a_new_log_and_the_dashboard_shows_it(): void
    {
        $this->grant('tax-readiness.index');
        $this->grant('tax-readiness.recalculate');

        $this->post(route('tax-readiness.recalculate'))->assertRedirect(route('tax-readiness.index'));

        $this->assertSame(1, \App\Models\TaxReadinessLog::count());
        $this->get(route('tax-readiness.index'))->assertOk();
    }
}
