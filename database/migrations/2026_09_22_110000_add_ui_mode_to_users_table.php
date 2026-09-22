<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P8.1 (Zero-Knowledge UX). Defaults to 'advanced' -- every existing user
 * sees NO change in behavior until they explicitly opt into 'simple' mode
 * themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ui_mode', 20)->default('advanced')->after('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ui_mode');
        });
    }
};
