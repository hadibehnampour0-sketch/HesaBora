<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-070: SettlementObligation/SettlementInstallment are deliberately generic
 * (polymorphic `source`) rather than cheque-specific, so a future release can
 * attach an obligation to a returned cheque, a manual customer-debt
 * agreement, etc. without a schema change. Neither table stores a paid/
 * settled amount or status -- those are always computed live from the
 * payment_allocations ledger (the single allocation ledger shared with
 * invoices) so there is no denormalized value that can drift out of sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_obligations', function (Blueprint $table) {
            $table->id();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->decimal('original_amount', 18, 2);
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });

        Schema::create('settlement_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obligation_id')->constrained('settlement_obligations')->cascadeOnDelete();
            $table->date('due_date');
            $table->decimal('amount', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_installments');
        Schema::dropIfExists('settlement_obligations');
    }
};
