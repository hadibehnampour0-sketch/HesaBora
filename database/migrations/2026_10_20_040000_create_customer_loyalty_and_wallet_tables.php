<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->unique();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedBigInteger('spend_per_point')->default(100000); // 100,000 Rials spend = 1 point
            $table->unsignedBigInteger('point_value_rials')->default(1000); // 1 point = 1,000 Rials credit
            $table->unsignedInteger('silver_points_threshold')->default(500);
            $table->unsignedInteger('gold_points_threshold')->default(2000);
            $table->unsignedInteger('vip_points_threshold')->default(5000);
            $table->decimal('silver_discount_percent', 5, 2)->default(2.00);
            $table->decimal('gold_discount_percent', 5, 2)->default(5.00);
            $table->decimal('vip_discount_percent', 5, 2)->default(10.00);
            $table->timestamps();
        });

        Schema::create('customer_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->bigInteger('balance')->default(0); // in Rials
            $table->unsignedInteger('points')->default(0);
            $table->string('tier', 32)->default('BRONZE'); // BRONZE, SILVER, GOLD, VIP
            $table->timestamps();

            $table->unique(['company_id', 'customer_id']);
        });

        Schema::create('customer_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('wallet_id')->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->string('type', 32)->index(); // POINTS_ACCRUED, POINTS_REDEEMED, WALLET_DEPOSIT, INVOICE_PAYMENT, ADJUSTMENT
            $table->bigInteger('amount')->default(0); // monetary change (+ or -)
            $table->integer('points_change')->default(0); // points change (+ or -)
            $table->bigInteger('balance_after')->default(0);
            $table->unsignedInteger('points_after')->default(0);
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_type', 64)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'wallet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wallet_transactions');
        Schema::dropIfExists('customer_wallets');
        Schema::dropIfExists('loyalty_settings');
    }
};
