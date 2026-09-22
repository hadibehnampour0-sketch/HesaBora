<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Organization Authorization & Workflow Governance phase (r12, 2026-09-20),
 * Workstream Q. Role assignment/removal happens via raw pivot-table queries
 * (model_has_roles), never firing an Eloquent model event, so it was
 * completely invisible to the existing generic ActivityLogService
 * model-event listener before this. Proves AppServiceProvider's
 * RoleAttached/RoleDetached listener actually records it.
 */
class RoleAssignmentAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_and_removing_a_role_is_recorded_in_the_audit_log(): void
    {
        $company = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $target->companies()->syncWithoutDetaching([$company->id]);

        $this->actingAs($actor);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($company->id);
        Role::firstOrCreate(['name' => __('Accountant')]);

        $target->assignRole(__('Accountant'));

        // This app renames Spatie activitylog's default columns at the DB
        // level (source/description/model_type/model_id/user_id/details
        // instead of log_name/event/subject_type/subject_id/causer_id/
        // properties) -- see ActivityLogService::index()'s own raw query
        // for the same mapping.
        $attached = Activity::where('source', 'security')
            ->where('description', 'role_attached')
            ->where('model_id', $target->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($attached, 'role_attached activity was not recorded');
        $this->assertSame($actor->id, $attached->user_id);
        $details = json_decode($attached->details, true);
        $this->assertSame([__('Accountant')], $details['roles']);
        $this->assertSame($company->id, $details['company_id']);

        $target->removeRole(__('Accountant'));

        $detached = Activity::where('source', 'security')
            ->where('description', 'role_detached')
            ->where('model_id', $target->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($detached, 'role_detached activity was not recorded');
        $detachedDetails = json_decode($detached->details, true);
        $this->assertSame([__('Accountant')], $detachedDetails['roles']);
    }
}
