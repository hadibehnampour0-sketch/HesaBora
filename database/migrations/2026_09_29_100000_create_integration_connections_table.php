<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dakhl_INTEGRATION_PLATFORMS_AND_POLICY_PLAN_2026-09-28.md. One row per external-platform
 * connection a company has set up (currently WooCommerce). Credentials are stored encrypted
 * (Laravel's `encrypted:array` cast, same principle as Company's Moadian certificate/key --
 * never plain in the database). `webhook_token` is the unauthenticated inbound URL's only
 * secret besides the HMAC signature -- long and random, never guessable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('source_system', 40);
            $table->string('name', 150);
            $table->string('webhook_token', 64)->unique();
            $table->text('credentials'); // encrypted JSON: site_url, consumer_key, consumer_secret, webhook_secret
            $table->foreignId('default_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('default_customer_group_id')->nullable()->constrained('customer_groups')->nullOnDelete();
            $table->decimal('price_tolerance_percent', 5, 2)->default(2.00);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_connections');
    }
};
