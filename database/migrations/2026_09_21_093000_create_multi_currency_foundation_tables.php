<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3.4 (Multi-Currency Foundation). Deliberately scoped as a FOUNDATION,
 * not a full multi-currency rewrite of Invoice/Payment/COGS: `value` on
 * `transactions` remains the base-currency posted amount and is left
 * completely untouched (the SUM(transactions.value)=0 balance invariant
 * this whole app enforces is never redefined) -- currency_id/
 * foreign_value/exchange_rate are purely ADDITIVE metadata recording what
 * the transaction was originally denominated in and at what rate it was
 * converted, for any future feature (multi-currency invoicing, FX
 * revaluation reports) to build on without a further schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('name', 100);
            $table->string('symbol', 10)->nullable();
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->decimal('rate', 18, 6);
            $table->date('effective_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['currency_id', 'effective_date']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('currency_id')->nullable()->after('value')->constrained()->nullOnDelete();
            $table->decimal('foreign_value', 18, 4)->nullable()->after('currency_id');
            $table->decimal('exchange_rate', 18, 6)->nullable()->after('foreign_value');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('currency_id');
            $table->dropColumn(['foreign_value', 'exchange_rate']);
        });
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
    }
};
