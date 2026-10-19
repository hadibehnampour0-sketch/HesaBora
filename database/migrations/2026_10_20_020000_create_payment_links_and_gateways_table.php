<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 50)->default('sandbox');
            $table->string('merchant_id', 150)->nullable();
            $table->string('api_key', 255)->nullable();
            $table->boolean('is_sandbox')->default(true);
            $table->boolean('is_active')->default(true);
            $table->foreignId('default_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'gateway']);
        });

        Schema::create('payment_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->decimal('amount', 15, 2);
            $table->string('gateway', 50)->default('sandbox');
            $table->string('gateway_token', 255)->nullable();
            $table->string('gateway_reference', 100)->nullable();
            $table->string('card_pan', 30)->nullable();
            $table->string('status', 30)->default('pending'); // pending, paid, expired, failed
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_links');
        Schema::dropIfExists('payment_gateway_configs');
    }
};
