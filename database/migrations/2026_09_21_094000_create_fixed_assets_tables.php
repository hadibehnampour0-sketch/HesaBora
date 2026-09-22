<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3.5 (Fixed Assets). Straight-line depreciation only (foundation scope).
 * accumulated_depreciation is never stored/denormalized on the asset row
 * itself -- always computed live as SUM(fixed_asset_depreciation_entries.
 * amount), the same "no value that can drift out of sync" rule the
 * Settlement domain (D-070) already established for this codebase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 18, 2);
            $table->decimal('salvage_value', 18, 2)->default(0);
            $table->unsignedSmallInteger('useful_life_months');
            $table->unsignedBigInteger('asset_subject_id');
            $table->unsignedBigInteger('depreciation_expense_subject_id');
            $table->unsignedBigInteger('accumulated_depreciation_subject_id');
            $table->unsignedTinyInteger('status')->default(1); // 1=active, 2=disposed
            $table->date('disposed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('fixed_asset_depreciation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained()->cascadeOnDelete();
            $table->date('period_date');
            $table->decimal('amount', 18, 2);
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();

            $table->unique(['fixed_asset_id', 'period_date'], 'fixed_asset_depreciation_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_depreciation_entries');
        Schema::dropIfExists('fixed_assets');
    }
};
