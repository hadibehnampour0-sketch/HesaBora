<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-081 stage 2.8D-1 -- the first extension of the UUID-scoped distributed
 * entity list (D-071 decision 2) since stage 2.3. `PaymentAllocation`
 * becomes its own independent Sync Entity, scoped under a Payment's own
 * `financial_event_uuid` but never altering it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->unsignedInteger('sync_version')->nullable()->after('uuid');
            $table->unsignedTinyInteger('sync_status')->nullable()->after('sync_version');
            $table->foreignId('origin_device_id')->nullable()->after('sync_status')->constrained('device_installations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origin_device_id');
            $table->dropColumn(['uuid', 'sync_version', 'sync_status']);
        });
    }
};
