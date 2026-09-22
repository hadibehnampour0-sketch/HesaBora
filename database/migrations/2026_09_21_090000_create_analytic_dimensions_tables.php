<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3.1 (Analytic Dimensions): lets a company tag ledger lines with
 * Branch/Project/Cost Center/Sales Channel WITHOUT cluttering the Chart of
 * Accounts (subjects) coding -- a dimension is orthogonal to which subject
 * (account) was affected, answering "which account, and which
 * branch/project/cost-center/channel drove it" as two independent
 * questions. Deliberately transaction-level (not document-level): a single
 * document can span multiple cost centers/projects across its lines, and
 * the account-affected/dimension-affected pairing is a per-line fact.
 * Many-to-many (not a single nullable FK per transaction) so a line can
 * carry more than one dimension TYPE at once (e.g. Branch=Tehran AND
 * Project=X AND Cost Center=Marketing on the same line) -- exactly the
 * "tagging" pattern the design brief allowed for as an alternative to a
 * rigid polymorphic single-dimension link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytic_dimensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('type');
            $table->string('code', 50);
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'type', 'code']);
        });

        Schema::create('analytic_dimension_transaction', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analytic_dimension_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['analytic_dimension_id', 'transaction_id'], 'analytic_dim_txn_unique');
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytic_dimension_transaction');
        Schema::dropIfExists('analytic_dimensions');
    }
};
