<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manufacturing Phase 1 ("Production Lite" / "تولید و بهای تمام‌شده"), per
 * Dakhl_MANUFACTURING_BOM_RESEARCH_AND_PLAN_2026-09-28.md §4 Phase 1. A recipe is a batch
 * formula: consuming its `output_quantity` worth of materials (each item's `quantity`, scaled
 * by however many batches are being produced) yields `output_quantity` units of the output
 * product. `waste_percent` on a material means "this much MORE of it is actually consumed than
 * the nominal recipe amount" (spillage/inefficiency), matching the ERPNext scrap-% convention
 * the research doc verified, not "this much of the output is lost."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete(); // the output product
            $table->string('name', 150);
            $table->decimal('output_quantity', 15, 4);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('production_recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('production_recipes')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete(); // the material product
            $table->decimal('quantity', 15, 4);
            $table->string('unit', 30)->nullable();
            $table->decimal('waste_percent', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_recipe_items');
        Schema::dropIfExists('production_recipes');
    }
};
