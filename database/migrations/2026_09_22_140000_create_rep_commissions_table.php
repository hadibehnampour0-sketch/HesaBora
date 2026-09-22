<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P12.4 (Field Operations Commission & Route Analytics Engine). One row
 * per (rep, period) -- `unique(['company_id', 'user_id', 'period_start',
 * 'period_end'])` makes RepCommissionEngineService::calculateForPeriod()
 * idempotent via updateOrCreate(), matching this app's established
 * "safe to re-run" convention (GenerateRecurringInvoicesCommand,
 * SyncMoadianStatusJob).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rep_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_settled_amount', 15, 2)->default(0);
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->decimal('commission_amount', 15, 2)->default(0);
            $table->string('tier_label', 40)->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['company_id', 'user_id', 'period_start', 'period_end'], 'rep_commissions_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rep_commissions');
    }
};
