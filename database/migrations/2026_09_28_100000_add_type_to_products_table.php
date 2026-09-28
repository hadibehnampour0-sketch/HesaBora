<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manufacturing/BOM readiness (Phase 0 of Dakhl_MANUFACTURING_BOM_RESEARCH_AND_PLAN_2026-09-28.md).
 * Purely additive: every existing row defaults to 'general', and nothing reads this column yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('type', 20)->default('general')->after('group');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
