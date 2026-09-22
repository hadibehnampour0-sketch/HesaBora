<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P7.1 (Approval Chains). Records who performed the FIRST approval step
 * of a two-step chain (invoice amount >= the company's configured
 * threshold) -- needed so the SECOND (final) approval can require a
 * DIFFERENT user than the first, on top of the ordinary
 * creator-cannot-approve (P7.2) check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('first_approved_by')->nullable()->after('creator_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('first_approved_by');
        });
    }
};
