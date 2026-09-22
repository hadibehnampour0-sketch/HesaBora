<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Workstream J (permission-matrix build-out): enumerating every registered
 * route surfaced that barryvdh/laravel-translation-manager's entire admin UI
 * (config/translation-manager.php's 'route' => ['middleware' => ...] was left
 * commented out) was registered with NO middleware at all -- not even the
 * 'web' group, so no session/CSRF, no auth, no permission check. Its
 * postEdit()/postAdd()/postDelete() write directly to ltm_translations with
 * no gate, letting anyone overwrite any UI string application-wide with no
 * login. Fixed by setting 'middleware' to the same
 * ['web', 'auth', 'permission:access-super-admin-panel'] convention the
 * app's own management dashboard already uses for this class of
 * system-configuration tool.
 */
class TranslationManagerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_the_translation_manager(): void
    {
        $this->get('/translations')->assertRedirect(route('login'));
    }

    public function test_guest_cannot_write_a_translation_value(): void
    {
        $this->post('/translations/edit/messages', [
            'name' => 'fa|some.key',
            'value' => 'attacker-controlled value',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('ltm_translations', [
            'group' => 'messages',
            'key' => 'some.key',
        ]);
    }

    public function test_guest_cannot_add_a_translation_key(): void
    {
        $this->post('/translations/add/messages', [
            'keys' => 'injected.key',
        ])->assertRedirect(route('login'));
    }

    public function test_authenticated_non_super_admin_cannot_access_the_translation_manager(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)->get('/translations')->assertForbidden();
    }

    public function test_super_admin_can_access_the_translation_manager(): void
    {
        $superAdmin = \App\Models\User::factory()->create();
        $superAdmin->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'access-super-admin-panel']));

        $this->actingAs($superAdmin)->get('/translations')->assertOk();
    }
}
