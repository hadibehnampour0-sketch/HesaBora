<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Lets a logged-in user set their own username and password, separate from
 * the super-admin-only Management\UserController flow. Requires the current
 * password to confirm identity before any change is applied.
 */
class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_page_shows_the_current_username(): void
    {
        $user = User::factory()->create(['username' => 'staffuser']);

        $response = $this->actingAs($user)->get(route('account.edit'));

        $response->assertOk()->assertSee('staffuser');
    }

    public function test_user_can_change_their_own_username(): void
    {
        $user = User::factory()->create(['username' => 'oldname', 'password' => bcrypt('password')]);

        $response = $this->actingAs($user)->put(route('account.update'), [
            'current_password' => 'password',
            'username' => 'newname',
        ]);

        $response->assertRedirect(route('account.edit'));
        $response->assertSessionHas('success');
        $this->assertSame('newname', $user->fresh()->username);
    }

    public function test_user_can_change_their_own_password(): void
    {
        $user = User::factory()->create(['username' => 'staffuser', 'password' => bcrypt('old-password')]);

        $response = $this->actingAs($user)->put(route('account.update'), [
            'current_password' => 'old-password',
            'username' => 'staffuser',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect(route('account.edit'));
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_username_can_stay_unchanged_without_supplying_a_new_password(): void
    {
        $user = User::factory()->create(['username' => 'staffuser', 'password' => bcrypt('password')]);

        $response = $this->actingAs($user)->put(route('account.update'), [
            'current_password' => 'password',
            'username' => 'staffuser',
        ]);

        $response->assertRedirect(route('account.edit'));
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_update_requires_the_correct_current_password(): void
    {
        $user = User::factory()->create(['username' => 'staffuser', 'password' => bcrypt('password')]);

        $response = $this->actingAs($user)->put(route('account.update'), [
            'current_password' => 'wrong-password',
            'username' => 'newname',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertSame('staffuser', $user->fresh()->username);
    }

    public function test_update_rejects_a_username_already_taken_by_another_user(): void
    {
        User::factory()->create(['username' => 'taken']);
        $user = User::factory()->create(['username' => 'mine', 'password' => bcrypt('password')]);

        $response = $this->actingAs($user)->put(route('account.update'), [
            'current_password' => 'password',
            'username' => 'taken',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertSame('mine', $user->fresh()->username);
    }

    public function test_update_allows_keeping_the_same_username(): void
    {
        $user = User::factory()->create(['username' => 'mine', 'password' => bcrypt('password')]);

        $response = $this->actingAs($user)->put(route('account.update'), [
            'current_password' => 'password',
            'username' => 'mine',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('account.edit'));
    }

    public function test_new_password_must_be_confirmed(): void
    {
        $user = User::factory()->create(['username' => 'staffuser', 'password' => bcrypt('password')]);

        $response = $this->actingAs($user)->put(route('account.update'), [
            'current_password' => 'password',
            'username' => 'staffuser',
            'password' => 'new-password-123',
            'password_confirmation' => 'does-not-match',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_guest_cannot_access_account_settings(): void
    {
        $this->get(route('account.edit'))->assertRedirect(route('login'));
        $this->put(route('account.update'), [])->assertRedirect(route('login'));
    }

    public function test_new_users_default_to_advanced_mode(): void
    {
        $user = User::factory()->create();

        $this->assertSame(\App\Enums\UiMode::ADVANCED, $user->ui_mode);
        $this->assertFalse($user->isSimpleMode());
    }

    public function test_a_user_can_switch_to_simple_mode_without_their_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch(route('account.update-ui-mode'), [
            'ui_mode' => 'simple',
        ]);

        $response->assertRedirect(route('account.edit'));
        $this->assertTrue($user->fresh()->isSimpleMode());
    }

    public function test_switching_back_to_advanced_mode_works(): void
    {
        $user = User::factory()->create(['ui_mode' => 'simple']);

        $this->actingAs($user)->patch(route('account.update-ui-mode'), ['ui_mode' => 'advanced']);

        $this->assertFalse($user->fresh()->isSimpleMode());
    }

    public function test_an_invalid_ui_mode_value_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch(route('account.update-ui-mode'), [
            'ui_mode' => 'super-fancy-mode',
        ]);

        $response->assertSessionHasErrors('ui_mode');
        $this->assertTrue($user->fresh()->ui_mode === \App\Enums\UiMode::ADVANCED);
    }

    public function test_user_can_log_in_with_the_username_they_set_for_themselves(): void
    {
        $user = User::factory()->create(['username' => 'oldname', 'password' => bcrypt('password')]);

        $this->actingAs($user)->put(route('account.update'), [
            'current_password' => 'password',
            'username' => 'freshusername',
        ]);

        $this->post(route('login'), [
            'username' => 'freshusername',
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user->fresh());
    }
}
