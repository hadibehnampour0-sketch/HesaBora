<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P12.2 (Tax Alert & Smart Notification Engine). Company-scoped, not
 * per-user -- a tax alert ("readiness score dropped", "invoice rejected
 * by Moadian") describes the company's tax posture, not one individual's
 * inbox, matching this app's existing TaxReadinessLog convention (a
 * company-wide record, not a per-user one). `dedup_key` plus the
 * company_id+dedup_key index is what TaxAlertEngineService uses to skip
 * re-raising the same condition within a 24h window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('severity', 20);
            $table->string('title');
            $table->text('message');
            $table->string('dedup_key', 150);
            $table->nullableMorphs('related');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at'], 'tax_alerts_company_date_index');
            $table->index(['company_id', 'dedup_key'], 'tax_alerts_company_dedup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_alerts');
    }
};
