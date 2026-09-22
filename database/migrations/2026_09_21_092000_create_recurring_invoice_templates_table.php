<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3.3 (Recurring Operations). One generic mechanism covers both
 * "recurring invoices" (SELL) and "recurring expenses" (BUY) -- this app
 * has no separate Expense entity (AncillaryCost is invoice-scoped
 * shipping/warehousing cost allocation, not a general recurring-bill
 * concept); a periodic vendor bill is structurally just a recurring BUY
 * invoice. `last_generated_at`/`next_run_date` together are the duplicate
 * -prevention guard: a run only fires when `next_run_date <= today`, and
 * `next_run_date` is only advanced AFTER a real Invoice is successfully
 * created, inside the same locked transaction, so a template can never be
 * processed twice for the same due date even if the scheduler is
 * triggered twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_invoice_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedTinyInteger('invoice_type');
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->json('items');
            $table->unsignedTinyInteger('frequency'); // 1=monthly, 2=quarterly, 3=yearly
            $table->unsignedSmallInteger('interval')->default(1);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_run_date');
            $table->date('last_generated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_invoice_templates');
    }
};
