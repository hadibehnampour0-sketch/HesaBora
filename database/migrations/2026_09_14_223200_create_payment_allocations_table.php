<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            // Polymorphic target (D-070): an Invoice or a SettlementInstallment share
            // this single allocation ledger so both go through the exact same
            // over-allocation/over-settlement guard instead of two parallel engines.
            $table->string('allocatable_type');
            $table->unsignedBigInteger('allocatable_id');
            $table->decimal('amount', 18, 2);
            $table->dateTime('allocated_at');
            $table->string('reference', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['allocatable_type', 'allocatable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
