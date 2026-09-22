<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-075 stage 2.7C-D -- `is_number_finalized` distinguishes a real,
 * immutable official number from the interim per-device local number stage
 * 2.7B assigns just to satisfy the legacy NOT NULL `number` column while a
 * lease-based Finalize did not yet exist. Once `is_number_finalized` is
 * true, `number` must never change again (enforced in
 * SyncPilotService::applyFinalize()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('is_number_finalized')->default(false)->after('number');
            $table->dateTime('number_finalized_at')->nullable()->after('is_number_finalized');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['is_number_finalized', 'number_finalized_at']);
        });
    }
};
