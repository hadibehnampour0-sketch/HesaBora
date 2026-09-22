<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Found while extending SettlementService for P3.2 (Payment Terms):
 * createObligationForCheque()'s check-then-create was never lock/
 * constraint-protected against two genuinely concurrent calls for the SAME
 * source, which could silently double the obligation's original_amount.
 * Now closed at both layers -- SettlementService locks the source row
 * before checking, and this unique constraint is the final DB-level
 * collision guard, matching the established pattern elsewhere in this
 * codebase (e.g. the numbering_leases/invoices race guards).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlement_obligations', function (Blueprint $table) {
            $table->unique(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('settlement_obligations', function (Blueprint $table) {
            $table->dropUnique(['source_type', 'source_id']);
        });
    }
};
