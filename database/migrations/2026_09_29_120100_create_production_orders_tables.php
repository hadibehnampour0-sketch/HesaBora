<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manufacturing Phase 1. A production order is a single, immediate "Produce now" action (no
 * draft/approval gate in v1 -- matches the "quick capture" pattern already used elsewhere in
 * Dakhl, e.g. quick-payment): `ManufacturingService::produce()` consumes materials, adds the
 * output, and writes this row in one transaction. `production_order_materials` snapshots what
 * was actually consumed and at what cost -- the recipe itself may change later, this row never
 * does (same reasoning as InvoiceItem snapshotting price/cost separately from the live Product).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained('production_recipes')->nullOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete(); // the output product
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_produced', 15, 4);
            $table->string('status', 20)->default('produced');
            $table->decimal('material_cost', 15, 2)->default(0);
            $table->decimal('labor_cost', 15, 2)->default(0);
            $table->decimal('overhead_cost', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->timestamp('produced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('production_order_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_consumed', 15, 4);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('line_cost', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_materials');
        Schema::dropIfExists('production_orders');
    }
};
