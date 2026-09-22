<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Organization Authorization & Workflow Governance phase (r12, 2026-09-20).
 *
 * Proves the 2026_09_20_100000_scope_spatie_roles_to_companies migration's
 * LEGACY-upgrade path: a database migrated before this phase (teams was
 * false, one global role assignment row per user) gets its role/permission
 * assignments expanded into per-company rows with zero permission loss, no
 * invented memberships, and no duplicates -- exactly the locked migration
 * decision. RefreshDatabase alone never exercises this path (a fresh test
 * database already has `teams => true` baked in from the first migration),
 * so this test explicitly rolls the schema back to the pre-phase flat shape,
 * seeds legacy-shaped data, and replays the migration -- following the same
 * `require ...; $migration->up();` pattern as
 * InvoiceDashboardPermissionMigrationTest.
 */
class CompanyScopedRoleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_expands_existing_role_assignments_per_company_membership_with_zero_loss(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $companyC = Company::create(['name' => 'Company C', 'fiscal_year' => 1403]);

        $superAdminRole = Role::firstOrCreate(['name' => 'Super-Admin']);
        $accountantRole = Role::firstOrCreate(['name' => __('Accountant')]);
        $sellerRole = Role::firstOrCreate(['name' => __('Seller')]);
        $panelPermission = Permission::firstOrCreate(['name' => 'access-super-admin-panel']);

        $superAdminUser = User::factory()->create();
        $multiCompanyUser = User::factory()->create();
        $singleCompanyUser = User::factory()->create();
        $noCompanyUser = User::factory()->create();

        $multiCompanyUser->companies()->syncWithoutDetaching([$companyA->id, $companyB->id, $companyC->id]);
        $singleCompanyUser->companies()->syncWithoutDetaching([$companyA->id]);

        $migration = require database_path('migrations/2026_09_20_100000_scope_spatie_roles_to_companies.php');

        // Roll back to the pre-phase flat schema and seed LEGACY-shaped rows
        // -- one global row per assignment, exactly like a real pre-teams
        // production database -- then replay up() to exercise the actual
        // upgrade path rather than the fresh-install path RefreshDatabase
        // already produced.
        $migration->down();

        DB::table('model_has_roles')->insert([
            ['role_id' => $superAdminRole->id, 'model_type' => User::class, 'model_id' => $superAdminUser->id],
            ['role_id' => $accountantRole->id, 'model_type' => User::class, 'model_id' => $multiCompanyUser->id],
            ['role_id' => $sellerRole->id, 'model_type' => User::class, 'model_id' => $singleCompanyUser->id],
            ['role_id' => $accountantRole->id, 'model_type' => User::class, 'model_id' => $noCompanyUser->id],
        ]);
        DB::table('model_has_permissions')->insert([
            ['permission_id' => $panelPermission->id, 'model_type' => User::class, 'model_id' => $multiCompanyUser->id],
        ]);

        $migration->up();

        // Multi-company user: one Accountant row per company they actually
        // belong to -- zero permission loss across every current membership.
        $this->assertEqualsCanonicalizing(
            [$companyA->id, $companyB->id, $companyC->id],
            DB::table('model_has_roles')->where('role_id', $accountantRole->id)->where('model_id', $multiCompanyUser->id)->pluck('company_id')->all()
        );

        // Single-company user: exactly one row, for the one company they
        // belong to -- no company they are not a member of is invented.
        $this->assertSame(
            [$companyA->id],
            DB::table('model_has_roles')->where('role_id', $sellerRole->id)->where('model_id', $singleCompanyUser->id)->pluck('company_id')->all()
        );

        // No-company user: nothing to scope the role to. Documented,
        // expected zero rows rather than guessing a company.
        $this->assertSame(0, DB::table('model_has_roles')->where('role_id', $accountantRole->id)->where('model_id', $noCompanyUser->id)->count());

        // Super-Admin: the reserved global sentinel (0), exactly one row,
        // regardless of company memberships -- and isSuperAdmin() confirms
        // it is usable from any company context.
        $this->assertSame(
            [0],
            DB::table('model_has_roles')->where('role_id', $superAdminRole->id)->where('model_id', $superAdminUser->id)->pluck('company_id')->all()
        );
        $this->assertTrue($superAdminUser->fresh()->isSuperAdmin());

        // Direct global permission grant: also the sentinel, not expanded
        // per company -- access-super-admin-panel must survive regardless
        // of active company (see User::hasGlobalPermission()).
        $this->assertSame(
            [0],
            DB::table('model_has_permissions')->where('permission_id', $panelPermission->id)->where('model_id', $multiCompanyUser->id)->pluck('company_id')->all()
        );
        $this->assertTrue($multiCompanyUser->fresh()->hasGlobalPermission('access-super-admin-panel'));

        // Rerun safety: replaying up() against an already-migrated schema
        // (Schema::hasColumn guard) must not duplicate or alter anything.
        $rolesCountBefore = DB::table('model_has_roles')->count();
        $permissionsCountBefore = DB::table('model_has_permissions')->count();
        $migration->up();
        $this->assertSame($rolesCountBefore, DB::table('model_has_roles')->count());
        $this->assertSame($permissionsCountBefore, DB::table('model_has_permissions')->count());
    }

    public function test_changing_a_role_in_one_company_does_not_affect_another_company(): void
    {
        Role::firstOrCreate(['name' => __('Accountant')]);
        Role::firstOrCreate(['name' => __('Seller')]);

        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $user = User::factory()->create();
        $user->companies()->syncWithoutDetaching([$companyA->id, $companyB->id]);

        $registrar = app(PermissionRegistrar::class);

        $registrar->setPermissionsTeamId($companyA->id);
        $user->assignRole(__('Accountant'));

        $registrar->setPermissionsTeamId($companyB->id);
        $user->assignRole(__('Seller'));

        $registrar->setPermissionsTeamId($companyA->id);
        $user->unsetRelation('roles');
        $this->assertTrue($user->hasRole(__('Accountant')));
        $this->assertFalse($user->hasRole(__('Seller')));

        $registrar->setPermissionsTeamId($companyB->id);
        $user->unsetRelation('roles');
        $this->assertTrue($user->hasRole(__('Seller')));
        $this->assertFalse($user->hasRole(__('Accountant')));

        // Changing the role in Company A must not touch Company B.
        $registrar->setPermissionsTeamId($companyA->id);
        $user->unsetRelation('roles');
        $user->syncRoles([__('Seller')]);

        $registrar->setPermissionsTeamId($companyA->id);
        $user->unsetRelation('roles');
        $this->assertTrue($user->hasRole(__('Seller')));
        $this->assertFalse($user->hasRole(__('Accountant')));

        $registrar->setPermissionsTeamId($companyB->id);
        $user->unsetRelation('roles');
        $this->assertTrue($user->hasRole(__('Seller')));
    }

    public function test_joining_a_new_company_does_not_implicitly_grant_a_role_held_elsewhere(): void
    {
        $customersIndex = Permission::firstOrCreate(['name' => 'customers.index']);
        $accountantRole = Role::firstOrCreate(['name' => __('Accountant')]);
        $accountantRole->givePermissionTo($customersIndex);

        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $user = User::factory()->create();
        $user->companies()->syncWithoutDetaching([$companyA->id]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($companyA->id);
        $user->assignRole(__('Accountant'));

        // Join a brand-new company -- membership alone must not carry any
        // role along with it.
        $user->companies()->syncWithoutDetaching([$companyB->id]);

        $registrar->setPermissionsTeamId($companyB->id);
        $user->unsetRelation('roles');
        $this->assertTrue($user->getRoleNames()->isEmpty());
        $this->assertFalse($user->can('customers.index'));
    }
}
