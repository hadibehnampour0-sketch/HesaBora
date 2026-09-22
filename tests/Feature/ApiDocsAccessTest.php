<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * P6 (API v1 Platform). Dedoc\Scramble's own RestrictedDocsAccess
 * middleware auto-allows /docs/api only in the 'local' environment
 * (config/scramble.php); the test suite runs under APP_ENV=testing
 * (phpunit.xml), so these tests actually exercise the `viewApiDocs` Gate
 * defined in AppServiceProvider, not the environment shortcut.
 */
class ApiDocsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_regular_user_cannot_view_the_api_docs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/docs/api')->assertForbidden();
    }

    public function test_a_super_admin_can_view_the_api_docs(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'Super-Admin']);
        $user->assignRole('Super-Admin');

        $this->actingAs($user)->get('/docs/api')->assertOk();
    }

    public function test_a_user_with_the_global_permission_can_view_the_api_docs(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));

        $this->actingAs($user)->get('/docs/api')->assertOk();
    }

    public function test_a_guest_cannot_view_the_api_docs(): void
    {
        // No `auth` middleware guards /docs/api -- only RestrictedDocsAccess
        // (config/scramble.php). Gate::allows('viewApiDocs') for a guest
        // short-circuits to false (the Gate closure type-hints a non-null
        // User, so Laravel never even invokes it for an unauthenticated
        // request), landing on the middleware's own abort(403), not a
        // login redirect.
        $this->get('/docs/api')->assertForbidden();
    }
}
