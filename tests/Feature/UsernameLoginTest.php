<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsernameLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_username_password_and_login_button_only(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertSee('name="username"', false)
            ->assertSee('type="text"', false)
            ->assertSee('autocomplete="username"', false)
            ->assertSee('name="password"', false)
            ->assertSee('type="password"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee(__('Login'))
            ->assertDontSee('name="email"', false)
            ->assertDontSee('name="remember"', false)
            ->assertDontSee(__('Remember Me'))
            ->assertDontSee('route(\'register\')')
            ->assertDontSee('password.request');
    }

    public function test_login_page_has_no_demo_credentials_or_promotional_links(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertDontSee('1234')
            ->assertDontSee('admin@dakhl.local')
            ->assertDontSee('github.com/Jooyeshgar')
            ->assertDontSee('FreeAmir');
    }

    public function test_user_can_log_in_with_username_and_password(): void
    {
        $user = User::factory()->create([
            'username' => 'staffuser',
            'email' => 'staffuser@example.com',
        ]);

        $response = $this->post(route('login'), [
            'username' => 'staffuser',
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_trims_whitespace_around_username(): void
    {
        $user = User::factory()->create([
            'username' => 'staffuser',
        ]);

        $response = $this->post(route('login'), [
            'username' => '  staffuser  ',
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rejects_unknown_username(): void
    {
        User::factory()->create(['username' => 'realuser']);

        $response = $this->post(route('login'), [
            'username' => 'nobody',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_login_rejects_invalid_password(): void
    {
        User::factory()->create(['username' => 'staffuser']);

        $response = $this->post(route('login'), [
            'username' => 'staffuser',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_usernames_are_unique_on_the_users_table(): void
    {
        User::factory()->create(['username' => 'taken']);

        $this->expectExceptionMessageMatches('/UNIQUE|unique/i');

        User::factory()->create(['username' => 'taken']);
    }

    public function test_database_seeder_creates_admin_with_expected_username_and_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@dakhl.local',
            'username' => 'admin',
        ]);

        $admin = User::where('email', 'admin@dakhl.local')->first();

        $this->assertNotNull($admin);
        $this->assertTrue(Hash::check('1234', $admin->password));
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->assertTrue($admin->hasRole('Super-Admin'));
    }

    public function test_seeded_admin_can_log_in_with_username(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->post(route('login'), [
            'username' => 'admin',
            'password' => '1234',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticated();
        $this->assertSame('admin@dakhl.local', auth()->user()->email);
    }

    /**
     * Security (Workstream T): the login endpoint had no rate limiting at
     * all -- no `throttle` route middleware (unlike `/forgot-password`,
     * `/verify-otp`, etc., which all already use `throttle:6,1`) and no
     * `ThrottlesLogins`-style lockout in `LoginController::login()`.
     * Every wrong-password attempt reaches a real `Auth::attempt()` call
     * with no limit, allowing unlimited password brute-forcing against
     * any account. Fixed by adding the same `throttle:6,1` convention the
     * app already established for its other sensitive auth endpoints.
     */
    public function test_repeated_failed_login_attempts_are_rate_limited(): void
    {
        User::factory()->create(['username' => 'staffuser']);

        for ($i = 0; $i < 6; $i++) {
            $this->post(route('login'), [
                'username' => 'staffuser',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post(route('login'), [
            'username' => 'staffuser',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }
}
