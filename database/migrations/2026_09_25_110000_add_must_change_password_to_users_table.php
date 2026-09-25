<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * R-01 (forced password change on first login). The seeded default admin
 * ships with a public, well-known password ("1234"); any account that still
 * has it must set a real one before using the app. New default admins are
 * created flagged by the seeder; this migration also flags EXISTING installs
 * whose admin still has the default password (a hash check per candidate row,
 * never a stored plaintext).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });

        DB::table('users')->where('email', 'admin@dakhl.local')->get(['id', 'password'])->each(function ($user) {
            if (Hash::check('1234', $user->password)) {
                DB::table('users')->where('id', $user->id)->update(['must_change_password' => true]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
