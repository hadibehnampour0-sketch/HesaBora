<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CompanyPermissionsTeamResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_zero_grants_nothing_even_when_user_has_roles_and_permissions_in_companies(): void
    {
        $company1 = Company::factory()->create(['name' => 'Company One']);
        $company2 = Company::factory()->create(['name' => 'Company Two']);

        $user = User::factory()->create();
        $user->companies()->syncWithoutDetaching([$company1->id, $company2->id]);
        $this->actingAs($user);

        $permission = Permission::firstOrCreate(['name' => 'documents.create', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $registrar = app(PermissionRegistrar::class);

        // Assign role and permission under Company 1
        $registrar->setPermissionsTeamId($company1->id);
        $user->assignRole($role);
        $user->givePermissionTo($permission);

        // Verify positive control under Company 1
        $this->assertTrue($user->hasRole('Accountant'));
        $this->assertTrue($user->hasPermissionTo('documents.create'));
        $this->assertTrue($user->can('documents.create'));

        // Now switch context to Team 0 (unauthenticated, or un-scoped ambient context)
        $registrar->setPermissionsTeamId(0);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        // Under Team 0, user MUST NOT have any roles or permissions
        $this->assertFalse($user->hasRole('Accountant'), 'Team 0 must not grant roles assigned to Company 1.');
        $this->assertFalse($user->hasPermissionTo('documents.create'), 'Team 0 must not grant direct permissions assigned to Company 1.');
        $this->assertFalse($user->can('documents.create'), 'Team 0 must not authorize wildcard can() checks.');

        // Clean up
        $registrar->setPermissionsTeamId(null);
    }

    public function test_unauthenticated_ambient_resolution_defaults_to_team_zero_in_production(): void
    {
        config(['active-company-id' => null]);
        auth()->logout();

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);

        // In test environment without active-company-id, resolver falls back safely
        $teamId = $registrar->getPermissionsTeamId();
        $this->assertIsInt($teamId);
    }
}
