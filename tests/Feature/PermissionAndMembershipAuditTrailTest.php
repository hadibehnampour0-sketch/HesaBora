<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Workstream Q closure (Epic Pack 1): the two remaining documented audit-trail
 * gaps from RoleAssignmentAuditTrailTest's own docblock -- direct permission
 * grants (givePermissionTo/revokePermissionTo, distinct from role assignment)
 * and company_user membership changes. Neither fires an Eloquent model event
 * (a plain custom pivot for company_user; Spatie's own dedicated events for
 * permissions), so both were invisible to the generic ActivityLogService
 * listener before this pass.
 */
class PermissionAndMembershipAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_granting_and_revoking_a_direct_permission_is_recorded_in_the_audit_log(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $this->actingAs($actor);

        Permission::firstOrCreate(['name' => 'access-super-admin-panel']);
        $target->givePermissionTo('access-super-admin-panel');

        $attached = Activity::where('source', 'security')
            ->where('description', 'permission_attached')
            ->where('model_id', $target->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($attached, 'permission_attached activity was not recorded');
        $this->assertSame($actor->id, $attached->user_id);
        $details = json_decode($attached->details, true);
        $this->assertSame(['access-super-admin-panel'], $details['permissions']);

        $target->revokePermissionTo('access-super-admin-panel');

        $detached = Activity::where('source', 'security')
            ->where('description', 'permission_detached')
            ->where('model_id', $target->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($detached, 'permission_detached activity was not recorded');
        $detachedDetails = json_decode($detached->details, true);
        $this->assertSame(['access-super-admin-panel'], $detachedDetails['permissions']);
    }

    public function test_changing_company_membership_via_management_user_controller_is_recorded(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1401]);

        $actor = User::factory()->create();
        Role::firstOrCreate(['name' => 'Super-Admin']);
        $actor->assignRole('Super-Admin');
        Role::firstOrCreate(['name' => __('Accountant')]);
        $target = User::factory()->create();
        $target->companies()->syncWithoutDetaching([$companyA->id]);

        $this->actingAs($actor)->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => [__('Accountant')],
            'company' => [$companyB->id],
        ]);

        $recorded = Activity::where('source', 'security')
            ->where('description', 'company_membership_changed')
            ->where('model_id', $target->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($recorded, 'company_membership_changed activity was not recorded');
        $this->assertSame($actor->id, $recorded->user_id);
        $details = json_decode($recorded->details, true);
        $this->assertSame([$companyB->id], $details['companies_attached']);
        $this->assertSame([$companyA->id], $details['companies_detached']);
    }

    public function test_creating_a_company_records_the_creator_membership(): void
    {
        $creator = User::factory()->create();
        Role::firstOrCreate(['name' => 'Super-Admin']);
        $creator->assignRole('Super-Admin');

        $this->actingAs($creator)->post(route('companies.store'), [
            'name' => 'New Co',
            'fiscal_year' => 1402,
        ]);

        $company = Company::where('name', 'New Co')->firstOrFail();

        $recorded = Activity::where('source', 'security')
            ->where('description', 'company_membership_changed')
            ->where('model_id', $creator->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($recorded, 'company_membership_changed activity was not recorded for company creation');
        $details = json_decode($recorded->details, true);
        $this->assertSame([$company->id], $details['companies_attached']);
    }
}
