<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * On a Persian keyboard layout, numeric keys type Farsi digits (۰-۹) rather
 * than ASCII ones. Every place a password is set or checked must normalize
 * digits (toEnglish()) the same way the login form and OTP verification
 * already do, or an all-digit password (like the seeded default admin
 * password) silently fails to match. Covers every such call site found by
 * grepping for Hash::check()/Auth::attempt()/password assignment.
 */
class PersianDigitPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_accepts_a_password_typed_with_farsi_digits(): void
    {
        $user = User::factory()->create(['username' => 'staffuser', 'password' => bcrypt('1234')]);

        $response = $this->post(route('login'), [
            'username' => 'staffuser',
            'password' => '۱۲۳۴',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_accepts_a_farsi_digit_username(): void
    {
        $user = User::factory()->create(['username' => '1234user', 'password' => bcrypt('password')]);

        $response = $this->post(route('login'), [
            'username' => '۱۲۳۴user',
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_account_settings_accepts_current_password_typed_with_farsi_digits(): void
    {
        $user = User::factory()->create(['username' => 'staffuser', 'password' => bcrypt('1234')]);

        $response = $this->actingAs($user)->put(route('account.update'), [
            'current_password' => '۱۲۳۴',
            'username' => 'newname',
        ]);

        $response->assertRedirect(route('account.edit'));
        $this->assertSame('newname', $user->fresh()->username);
    }

    public function test_account_settings_stores_a_new_password_typed_with_farsi_digits_as_english_digits(): void
    {
        $user = User::factory()->create(['username' => 'staffuser', 'password' => bcrypt('password')]);

        $this->actingAs($user)->put(route('account.update'), [
            'current_password' => 'password',
            'username' => 'staffuser',
            'password' => '۱۲۳۴۵۶۷۸',
            'password_confirmation' => '۱۲۳۴۵۶۷۸',
        ]);

        $this->assertTrue(Hash::check('12345678', $user->fresh()->password));
    }

    public function test_password_reset_stores_a_new_password_typed_with_farsi_digits_as_english_digits(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $resetToken = null;

        $this->post(route('password.email'), ['email' => $user->email]);
        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use (&$resetToken) {
            $resetToken = $notification->token;

            return true;
        });

        $this->post(route('password.update'), [
            'token' => $resetToken,
            'email' => $user->email,
            'password' => '۱۲۳۴۵۶۷۸',
            'password_confirmation' => '۱۲۳۴۵۶۷۸',
        ]);

        $this->assertTrue(Hash::check('12345678', $user->fresh()->password));
    }

    public function test_registration_stores_a_password_typed_with_farsi_digits_as_english_digits(): void
    {
        config(['app.registration' => true]);

        $this->post(route('register.email'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => '۱۲۳۴۵۶۷۸',
            'password_confirmation' => '۱۲۳۴۵۶۷۸',
        ]);

        $user = User::where('email', 'newuser@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('12345678', $user->password));
    }

    public function test_management_user_creation_stores_a_password_typed_with_farsi_digits_as_english_digits(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'Super-Admin']));
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'users.store']));
        $company = Company::factory()->create();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Managed User',
            'email' => 'managed@example.com',
            'password' => '۱۲۳۴۵۶۷۸',
            'password_confirmation' => '۱۲۳۴۵۶۷۸',
            'role' => [Role::firstOrCreate(['name' => 'Employee'])->name],
            'company' => [$company->id],
        ]);

        $user = User::where('email', 'managed@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('12345678', $user->password));
    }
}
