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
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->text('license_key');
            $table->string('license_hash', 64)->unique();
            $table->string('edition', 32)->default('trial'); // trial, basic, professional, enterprise, custom
            $table->string('customer_name')->default('کاربر سامانه دخل');
            $table->string('customer_national_id', 32)->nullable();
            $table->string('hardware_fingerprint', 64)->nullable();
            $table->integer('max_companies')->default(1); // -1 = unlimited
            $table->integer('max_users')->default(2);     // -1 = unlimited
            $table->integer('max_devices')->default(1);   // -1 = unlimited
            $table->json('modules');                       // Array of enabled module keys or ['*']
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();  // null = lifetime
            $table->unsignedInteger('grace_period_days')->default(7);
            $table->boolean('is_active')->default(true);
            $table->text('signature');
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
