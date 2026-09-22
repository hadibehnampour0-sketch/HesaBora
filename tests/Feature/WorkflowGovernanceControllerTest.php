<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkflowGovernanceControllerTest extends TestCase
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
    }

    private function grant(string $permission): void
    {
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
    }

    public function test_a_user_without_permission_cannot_view_the_settings_page(): void
    {
        $this->get(route('workflow-governance.edit'))->assertForbidden();
    }

    public function test_a_user_with_permission_can_view_the_settings_page(): void
    {
        $this->grant('workflow-governance.edit');

        $this->get(route('workflow-governance.edit'))
            ->assertOk()
            ->assertSee(__('Workflow Governance'));
    }

    public function test_updating_settings_persists_and_is_reflected_immediately(): void
    {
        $this->grant('workflow-governance.edit');
        $this->grant('workflow-governance.update');

        $response = $this->put(route('workflow-governance.update'), [
            'self_approval_prevented' => '1',
            'invoice_approval_chain_threshold' => '500000',
        ]);

        $response->assertRedirect(route('workflow-governance.edit'));

        $this->assertDatabaseHas('configs', [
            'key' => 'self_approval_prevented',
            'company_id' => $this->companyId,
            'value' => '1',
        ]);
        $this->assertDatabaseHas('configs', [
            'key' => 'invoice_approval_chain_threshold',
            'company_id' => $this->companyId,
            'value' => '500000',
        ]);

        $this->assertTrue((bool) config('amir.self_approval_prevented'));
        $this->assertSame(500000.0, (float) config('amir.invoice_approval_chain_threshold'));
    }

    public function test_clearing_the_threshold_disables_the_approval_chain(): void
    {
        $this->grant('workflow-governance.edit');
        $this->grant('workflow-governance.update');

        $this->put(route('workflow-governance.update'), [
            'self_approval_prevented' => '0',
            'invoice_approval_chain_threshold' => '500000',
        ]);

        $this->put(route('workflow-governance.update'), [
            'self_approval_prevented' => '0',
        ]);

        $this->assertDatabaseMissing('configs', [
            'key' => 'invoice_approval_chain_threshold',
            'company_id' => $this->companyId,
        ]);
    }
}
