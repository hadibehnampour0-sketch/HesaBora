<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-3 (docs/project-context/Dakhl_TAX_MODULE_COMPLETENESS_PLAN_2026-09-25.md). Records only that
 * a period was marked filed -- the due date and other status derivations are computed at read
 * time (TaxFilingCalendarService) from data that already exists, never stored/duplicated here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_filing_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('jalali_year');
            $table->unsignedTinyInteger('quarter');
            $table->timestamp('filed_at');
            $table->foreignId('filed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 1000)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'jalali_year', 'quarter'], 'tax_filing_records_company_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_filing_records');
    }
};
