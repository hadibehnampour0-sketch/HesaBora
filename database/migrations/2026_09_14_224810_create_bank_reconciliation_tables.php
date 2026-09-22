<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->string('original_filename')->nullable();
            $table->unsignedInteger('lines_count')->default(0);
            $table->unsignedInteger('duplicate_lines_count')->default(0);
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_id')->constrained('bank_statements')->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 18, 2); // positive = inflow, negative = outflow
            $table->string('description', 500)->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('dedupe_hash', 64);
            $table->foreignId('reconciled_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->dateTime('reconciled_at')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['bank_account_id', 'dedupe_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statements');
    }
};
