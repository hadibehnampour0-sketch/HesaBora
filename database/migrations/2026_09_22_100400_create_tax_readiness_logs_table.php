<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P9.7 (Tax Readiness Score). One row per calculation, so the dashboard
 * can show a trend over time -- not a single mutable "current score."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_readiness_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->string('status', 20);
            $table->json('metrics_breakdown');
            $table->json('recommendations');
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->index(['company_id', 'calculated_at'], 'tax_readiness_logs_company_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_readiness_logs');
    }
};
