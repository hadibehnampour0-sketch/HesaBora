<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3.2 (Payment Terms & Installments). Deliberately does NOT introduce a
 * new installment-tracking mechanism -- applying a term to an Invoice
 * creates a SettlementObligation (source_type=Invoice, D-070's own
 * anticipated extension point) plus SettlementInstallment rows, reusing
 * the exact same generic ledger Cheque-secured settlements already use.
 * payment_terms/payment_term_lines are the reusable TEMPLATE only; no
 * paid/settled amount is stored here either -- same "always computed live
 * from payment_allocations" rule as the rest of the Settlement domain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('payment_term_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_term_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('days_offset');
            $table->decimal('percentage', 5, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_term_lines');
        Schema::dropIfExists('payment_terms');
    }
};
