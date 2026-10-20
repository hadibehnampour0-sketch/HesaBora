<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financing_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('name');
            $table->string('calculation_method', 32)->default('MARKET_MONTHLY'); // MARKET_MONTHLY, BANKING_AMORTIZATION
            $table->decimal('monthly_interest_rate', 5, 2)->default(3.00); // 3% per month
            $table->decimal('annual_interest_rate', 5, 2)->nullable(); // e.g. 23%
            $table->unsignedInteger('default_tenor_months')->default(12);
            $table->decimal('late_fee_rate_percent', 5, 2)->default(6.00); // e.g. 6% annual delay damages
            $table->unsignedInteger('grace_period_days')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('installment_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('contract_number', 64)->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('invoice_id')->nullable()->index();
            $table->unsignedBigInteger('plan_id')->nullable()->index();
            $table->bigInteger('total_price'); // کل مبلغ اولیه کالا یا فاکتور
            $table->bigInteger('down_payment')->default(0); // پیش‌پرداخت نقدی
            $table->bigInteger('principal_amount'); // اصل مبلغ تسهیلات (total_price - down_payment)
            $table->bigInteger('total_interest'); // کل سود و کارمزد
            $table->bigInteger('total_amount'); // کل مبلغ قرارداد (principal + interest)
            $table->unsignedInteger('tenor_months'); // تعداد اقساط
            $table->bigInteger('monthly_installment_amount'); // مبلغ هر قسط
            $table->date('start_date');
            $table->string('status', 32)->default('ACTIVE'); // ACTIVE, SETTLED, DEFAULTED, CANCELLED
            $table->unsignedBigInteger('interest_income_subject_id')->nullable();
            $table->unsignedBigInteger('penalty_income_subject_id')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'contract_number']);
        });

        Schema::create('installment_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id')->index();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedInteger('installment_number'); // 1..N
            $table->date('due_date');
            $table->bigInteger('principal_portion');
            $table->bigInteger('interest_portion');
            $table->bigInteger('amount'); // principal + interest
            $table->bigInteger('paid_amount')->default(0);
            $table->bigInteger('late_fee_amount')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->string('status', 32)->default('UNPAID'); // UNPAID, PAID, OVERDUE, PARTIALLY_PAID
            $table->string('sayad_cheque_number', 32)->nullable(); // چک صیادی قسط
            $table->unsignedBigInteger('document_id')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'installment_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_schedules');
        Schema::dropIfExists('installment_contracts');
        Schema::dropIfExists('financing_plans');
    }
};
