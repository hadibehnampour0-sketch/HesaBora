<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capillary distribution: a "visitor" is a company user working the field, optionally bound to
 * their own (van) warehouse. Additive; the van warehouse is an ordinary Warehouse row (guardrail #53).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribution_visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'user_id'], 'dist_visitors_company_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_visitors');
    }
};
