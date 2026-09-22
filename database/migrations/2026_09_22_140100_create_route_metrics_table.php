<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P12.4 (Field Operations Commission & Route Analytics Engine). One row
 * per (route, period) -- see rep_commissions migration's docblock for the
 * same idempotent-upsert rationale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_id')->constrained('sales_routes')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('visit_completion_rate', 5, 2)->default(0);
            $table->decimal('average_invoice_value', 15, 2)->default(0);
            $table->decimal('cash_collection_ratio', 5, 2)->default(0);
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['company_id', 'route_id', 'period_start', 'period_end'], 'route_metrics_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_metrics');
    }
};
