<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('portal_leads', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name', 100);
            $table->string('phone', 30);
            $table->string('business_type', 100)->nullable();
            $table->string('status', 30)->default('new'); // new, contacted, converted, cancelled
            $table->string('ip_address', 45)->nullable();
            $table->text('notes')->nullable();
            $table->text('trial_license_key')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('portal_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_reference', 40)->unique();
            $table->string('plan', 50);
            $table->string('customer_name', 100);
            $table->string('phone', 30);
            $table->string('company_name', 100)->nullable();
            $table->string('hardware_fingerprint', 100)->nullable();
            $table->unsignedInteger('extra_companies')->default(0);
            $table->unsignedInteger('extra_users')->default(0);
            $table->unsignedBigInteger('total_price')->default(0);
            $table->string('status', 30)->default('pending'); // pending, paid, completed, cancelled
            $table->text('issued_license_key')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portal_orders');
        Schema::dropIfExists('portal_leads');
    }
};
