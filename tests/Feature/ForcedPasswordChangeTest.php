<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * R-01: the seeded default admin has a public password, so an account flagged
 * `must_change_password` is confined to the account page until it sets a real one.
 */
class ForcedPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private function flaggedUser(string $password = '1234'): User
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'username' => 'admin',
            'password' => bcrypt($password),
            'must_change_password' => true,
        ]);
        $company->users()->syncWithoutDetaching($user);
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'industry-profiles.index']));
        config(['active-company-id' => $company->id]);

        return $user;
    }

    public function test_a_flagged_user_is_redirected_to_the_account_page_from_any_other_page(): void
    {
        $user = $this->flaggedUser();

        $this->actingAs($user)->get(route('industry-profiles.index'))
            ->assertRedirect(route('account.edit'))
            ->assertSessionHas('warning');
    }

    public function test_a_flagged_user_still_reaches_the_account_page_and_logout(): void
    {
        $user = $this->flaggedUser();

        $this->actingAs($user)->get(route('account.edit'))->assertOk();
        $this->actingAs($user)->get(route('logout'))->assertRedirect();
    }

    public function test_a_flagged_user_gets_403_json_on_ajax_requests(): void
    {
        $user = $this->flaggedUser();

        $this->actingAs($user)->getJson(route('industry-profiles.index'))->assertForbidden();
    }

    public function test_an_unflagged_user_is_unaffected(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $company->users()->syncWithoutDetaching($user);
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'industry-profiles.index']));
        config(['active-company-id' => $company->id]);

        $this->actingAs($user)->withCookies(['active-company-id' => (string) $company->id])
            ->get(route('industry-profiles.index'))->assertOk();
    }

    public function test_the_change_requires_a_new_password_not_a_blank_one(): void
    {
        $user = $this->flaggedUser();

        $this->actingAs($user)->put(route('account.update'), [
            'current_password' => '1234',
            'username' => 'admin',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_the_new_password_cannot_be_the_default_or_the_current_one(): void
    {
        $user = $this->flaggedUser('default-pass-1');

        $this->actingAs($user)->put(route('account.update'), [
            'current_password' => 'default-pass-1',
            'username' => 'admin',
            'password' => 'default-pass-1',
            'password_confirmation' => 'default-pass-1',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_a_valid_change_clears_the_flag_and_unlocks_the_app(): void
    {
        $user = $this->flaggedUser();

        $this->actingAs($user)->put(route('account.update'), [
            'current_password' => '1234',
            'username' => 'admin',
            'password' => 'a-real-password-9',
            'password_confirmation' => 'a-real-password-9',
        ])->assertRedirect(route('account.edit'));

        $fresh = $user->fresh();
        $this->assertFalse($fresh->must_change_password);
        $this->assertTrue(Hash::check('a-real-password-9', $fresh->password));

        $this->actingAs($fresh)->withCookies(['active-company-id' => (string) config('active-company-id')])
            ->get(route('industry-profiles.index'))->assertOk();
    }

    public function test_the_migration_backfill_flags_only_a_default_admin_that_still_has_the_default_password(): void
    {
        $default = User::factory()->create(['email' => 'admin@dakhl.local', 'password' => bcrypt('1234')]);
        $changed = User::factory()->create(['email' => 'other@dakhl.local', 'password' => bcrypt('1234')]);
        $strong = User::factory()->create(['email' => 'admin-two@dakhl.local', 'password' => bcrypt('x-strong-pass-1')]);

        // Put the schema back to its pre-migration state, then run the real migration.
        Schema::table('users', fn ($table) => $table->dropColumn('must_change_password'));
        (require database_path('migrations/2026_09_25_110000_add_must_change_password_to_users_table.php'))->up();

        $this->assertTrue((bool) DB::table('users')->where('id', $default->id)->value('must_change_password'));
        $this->assertFalse((bool) DB::table('users')->where('id', $changed->id)->value('must_change_password'));
        $this->assertFalse((bool) DB::table('users')->where('id', $strong->id)->value('must_change_password'));
    }
}
