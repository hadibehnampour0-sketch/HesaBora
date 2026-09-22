<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P7.2 (Segregation of Duties). AncillaryCost had no creator-tracking
 * column of any kind before this -- nullable/backward-compatible so
 * existing rows (creator unknown) are simply exempt from the self-approval
 * check rather than falsely flagged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ancillary_costs', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ancillary_costs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
