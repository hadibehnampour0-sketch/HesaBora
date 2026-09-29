<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-visitor commission override (RepCommissionEngineService's tiers stay the company-wide
 * default; a visitor with their own agreed rate overrides it here). Additive: null means "use
 * the tier calculation, unchanged" -- no existing behavior changes for a visitor without one set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distribution_visitors', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('distribution_visitors', function (Blueprint $table) {
            $table->dropColumn('commission_rate');
        });
    }
};
