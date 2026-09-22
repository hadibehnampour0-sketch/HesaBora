<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P11 (Field Sales & Distribution -- "FieldOperations"). Backend-only for
 * this pass, per the owner's explicit scope decision: the mobile
 * app/hardware layer is a separate architecture document, not code here.
 *
 * `sales_routes` / `route_customers`: a route is a named, ordered set of
 * customers a rep visits. `visit_plans`: one row per (rep, date) --
 * deliberately no separate per-stop table this pass; which customers were
 * actually visited is derivable from Invoices/Documents created that day
 * against the route's customers, not tracked as a separate checklist yet.
 * `daily_settlements`: end-of-day cash/POS/cheque reconciliation for a rep
 * or driver. `promotions`: a pure calculator input (see
 * PromotionEngineService) -- applying a suggested discount/bonus into an
 * actual Invoice still goes through the existing, unchanged Invoice
 * creation flow; this table/engine never writes to invoices directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('code', 20);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'sales_routes_company_code_unique');
        });

        Schema::create('route_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('sales_routes')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('visit_priority')->default(0);
            $table->string('preferred_visit_days', 50)->nullable();
            $table->timestamps();

            $table->unique(['route_id', 'customer_id'], 'route_customers_route_customer_unique');
        });

        Schema::create('visit_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('sales_routes')->nullOnDelete();
            $table->date('plan_date');
            $table->string('status', 20)->default('planned');
            $table->timestamps();

            $table->unique(['company_id', 'user_id', 'plan_date'], 'visit_plans_user_date_unique');
        });

        Schema::create('daily_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('settlement_date');
            $table->decimal('total_invoiced_amount', 15, 2)->default(0);
            $table->decimal('cash_collected', 15, 2)->default(0);
            $table->decimal('pos_collected', 15, 2)->default(0);
            $table->decimal('cheque_collected', 15, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'user_id', 'settlement_date'], 'daily_settlements_user_date_unique');
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 20);
            $table->foreignId('customer_group_id')->nullable()->constrained('customer_groups')->nullOnDelete();
            $table->json('rules');
            $table->boolean('is_active')->default(true);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active'], 'promotions_company_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('daily_settlements');
        Schema::dropIfExists('visit_plans');
        Schema::dropIfExists('route_customers');
        Schema::dropIfExists('sales_routes');
    }
};
