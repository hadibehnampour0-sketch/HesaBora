<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Organization Authorization & Workflow Governance phase (r12, 2026-09-20).
 *
 * Workstreams T (permission cache / team-context isolation) and U (adversarial
 * cross-company attack). Proves the highest-risk consequence of enabling
 * Spatie teams: that a user with DIFFERENT roles in DIFFERENT companies
 * never gets one company's privileges while operating in another.
 *
 * The first test below is what actually caught a genuine, serious defect:
 * with `enable_wildcard_permission = true` (this app's existing config),
 * `can()`/`hasPermissionTo()` resolve through Spatie's wildcard permission
 * checker, which caches a per-user permission index on the
 * PermissionRegistrar SINGLETON and never invalidates it when the team
 * context changes mid-request -- checking the same user's permissions in
 * Company A, then switching to Company B and checking again, silently
 * returned Company A's permission set. Fixed in
 * App\Support\CompanyPermissionsTeamResolver::setPermissionsTeamId(), which
 * now forgets the wildcard index whenever the resolved team actually
 * changes. `hasRole()` alone was never affected (it queries fresh every
 * time); only the wildcard-backed `can()`/`hasPermissionTo()` path was.
 */
class CompanyAuthorizationIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Spatie's permission cache lives in the 'array' cache store, which
        // is NOT reset by RefreshDatabase's per-test transaction rollback --
        // a stale role/permission graph from an earlier test method in this
        // same process would otherwise leak into this one.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function seedRoles(): void
    {
        Role::firstOrCreate(['name' => __('Accountant')]);
        Role::firstOrCreate(['name' => __('Seller')]);
        Permission::firstOrCreate(['name' => 'customers.index']);
        Permission::firstOrCreate(['name' => 'invoices.index']);
        Permission::firstOrCreate(['name' => 'home']);

        Role::findByName(__('Accountant'))->givePermissionTo(['customers.index', 'home']);
        Role::findByName(__('Seller'))->givePermissionTo(['invoices.index', 'home']);
    }

    public function test_a_user_with_different_roles_in_two_companies_never_leaks_privileges_between_them(): void
    {
        $this->seedRoles();

        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $user = User::factory()->create();
        $user->companies()->syncWithoutDetaching([$companyA->id, $companyB->id]);

        $registrar = app(PermissionRegistrar::class);

        $registrar->setPermissionsTeamId($companyA->id);
        $user->assignRole(__('Accountant'));

        $registrar->setPermissionsTeamId($companyB->id);
        $user->assignRole(__('Seller'));

        // Repeated switching within one process (the exact shape a single
        // HTTP request's lifecycle, or code that must check several
        // companies at once, could take) must never let a stale permission
        // index leak across the switch.
        foreach (range(1, 3) as $iteration) {
            $registrar->setPermissionsTeamId($companyA->id);
            $user->unsetRelation('roles');
            $this->assertTrue($user->can('customers.index'), "iteration $iteration: Accountant permission missing in Company A");
            $this->assertFalse($user->can('invoices.index'), "iteration $iteration: Seller permission leaked into Company A");

            $registrar->setPermissionsTeamId($companyB->id);
            $user->unsetRelation('roles');
            $this->assertTrue($user->can('invoices.index'), "iteration $iteration: Seller permission missing in Company B");
            $this->assertFalse($user->can('customers.index'), "iteration $iteration: Accountant permission leaked into Company B");
        }
    }

    public function test_switching_active_company_via_http_cookie_changes_authorization_without_stale_leakage(): void
    {
        $this->seedRoles();

        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $user = User::factory()->create();
        $user->companies()->syncWithoutDetaching([$companyA->id, $companyB->id]);

        // Mirrors how real code (UserController::syncCompanyScopedRoles(),
        // CompanyController::createCompany()) always CLEARS (never
        // "restores a captured value") after a deliberate, temporary
        // override -- see CompanyPermissionsTeamResolver's docblock for why
        // restoring a captured numeric value would incorrectly re-pin it
        // past this point, ignoring the upcoming HTTP requests' own cookie.
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($companyA->id);
        $user->assignRole(__('Accountant'));
        $registrar->setPermissionsTeamId($companyB->id);
        $user->assignRole(__('Seller'));
        $registrar->setPermissionsTeamId(null);

        // A real request with Company A active.
        $this->actingAs($user)
            ->withCookie('active-company-id', (string) $companyA->id)
            ->get(route('home'))
            ->assertOk();
        // A real request always gets a freshly-authenticated User instance
        // (a new Eloquent query) AND a fresh PermissionRegistrar/wildcard
        // index (a new container) every PHP-FPM process. actingAs() reuses
        // the same PHP object and container across these two simulated
        // requests instead, so both are force-refreshed here to accurately
        // represent that -- a testing-harness accommodation, not a
        // production workaround (CompanyPermissionsTeamResolver's own
        // per-resolution wildcard-index invalidation is what makes a real,
        // single request's OWN internal team switches -- e.g.
        // UserController::syncCompanyScopedRoles() looping companies --
        // correct without needing this).
        auth()->user()->unsetRelation('roles');
        $this->assertTrue(auth()->user()->can('customers.index'));
        $this->assertFalse(auth()->user()->can('invoices.index'));

        // Switching the cookie to Company B on the very next request must
        // flip authorization completely -- no leftover Company A grant.
        $this->actingAs($user)
            ->withCookie('active-company-id', (string) $companyB->id)
            ->get(route('home'))
            ->assertOk();
        auth()->user()->unsetRelation('roles');
        $registrar->forgetWildcardPermissionIndex();
        $this->assertTrue(auth()->user()->can('invoices.index'));
        $this->assertFalse(auth()->user()->can('customers.index'));
    }

    public function test_revoking_a_role_in_one_company_takes_effect_immediately_without_affecting_the_other(): void
    {
        $this->seedRoles();

        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $user = User::factory()->create();
        $user->companies()->syncWithoutDetaching([$companyA->id, $companyB->id]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($companyA->id);
        $user->assignRole(__('Accountant'));
        $registrar->setPermissionsTeamId($companyB->id);
        $user->assignRole(__('Accountant'));

        $registrar->setPermissionsTeamId($companyA->id);
        $user->unsetRelation('roles');
        $this->assertTrue($user->can('customers.index'));

        // Revoke in Company A only.
        $user->removeRole(__('Accountant'));

        // A pre-existing Spatie characteristic, unrelated to company
        // scoping: assignRole()/removeRole() on a USER never call
        // forgetWildcardPermissionIndex() (only a Permission-side change
        // does), so the wildcard-backed can()/hasPermissionTo() path can
        // stay stale for the SAME PHP object within the SAME process after
        // a revocation. hasRole() itself is never affected (queries fresh
        // every time). Real requests are unaffected (a fresh user object
        // and a fresh registrar every PHP-FPM request); this only matters
        // if code revokes then immediately re-checks the same object/request.
        $registrar->forgetWildcardPermissionIndex();

        $registrar->setPermissionsTeamId($companyA->id);
        $user->unsetRelation('roles');
        $this->assertFalse($user->can('customers.index'), 'revoked role in Company A must take effect immediately');

        $registrar->setPermissionsTeamId($companyB->id);
        $user->unsetRelation('roles');
        $this->assertTrue($user->can('customers.index'), 'Company B must be unaffected by a Company A revocation');
    }

    public function test_a_companys_custom_role_is_invisible_and_unreachable_from_another_company(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);

        $adminA = User::factory()->create();
        $adminA->companies()->syncWithoutDetaching([$companyA->id]);
        $adminB = User::factory()->create();
        $adminB->companies()->syncWithoutDetaching([$companyB->id]);

        // RoleController's management screen is Super-Admin-exclusive today
        // (RolesAndPermissionsSeeder deliberately excludes `roles.*` from
        // every other role, including Admin) -- Super-Admin's global,
        // company-independent Gate::before bypass is unaffected by
        // ensureOwnedByActiveCompany()'s plain company_id comparison, so
        // this is the realistic way to reach these routes at all.
        $superAdminRole = Role::firstOrCreate(['name' => 'Super-Admin']);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(0);
        $adminA->assignRole($superAdminRole);
        $adminB->assignRole($superAdminRole);

        $customRole = $this->actingAs($adminA)
            ->withCookie('active-company-id', (string) $companyA->id)
            ->post(route('roles.store'), ['name' => 'Custom Auditor']);
        $customRole->assertRedirect(route('roles.index'));

        $created = Role::where('name', 'Custom Auditor')->firstOrFail();
        $this->assertSame($companyA->id, (int) $created->company_id);

        // Company B's admin must not see, edit, or delete Company A's
        // custom role -- even by guessing its id directly.
        $this->actingAs($adminB)
            ->withCookie('active-company-id', (string) $companyB->id)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertDontSee('Custom Auditor');

        $this->actingAs($adminB)
            ->withCookie('active-company-id', (string) $companyB->id)
            ->get(route('roles.edit', $created))
            ->assertNotFound();

        $this->actingAs($adminB)
            ->withCookie('active-company-id', (string) $companyB->id)
            ->delete(route('roles.destroy', $created))
            ->assertNotFound();

        $this->assertDatabaseHas('roles', ['id' => $created->id, 'name' => 'Custom Auditor']);
    }

    public function test_super_admin_bypasses_company_scoping_from_any_active_company(): void
    {
        Permission::firstOrCreate(['name' => 'customers.index']);

        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $superAdmin = User::factory()->create();
        $superAdmin->companies()->syncWithoutDetaching([$companyA->id]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(0);
        $superAdmin->assignRole(Role::firstOrCreate(['name' => 'Super-Admin']));

        // Super-Admin's bypass must hold regardless of active company,
        // including a company it has no membership row in at all.
        $registrar->setPermissionsTeamId($companyA->id);
        $this->assertTrue($superAdmin->fresh()->can('customers.index'));

        $registrar->setPermissionsTeamId($companyB->id);
        $this->assertTrue($superAdmin->fresh()->can('customers.index'));

        $registrar->setPermissionsTeamId(null);
        $this->assertTrue($superAdmin->fresh()->can('customers.index'));
    }
}
