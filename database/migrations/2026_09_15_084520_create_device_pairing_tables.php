<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-071 decision 6: device/company identity uses Pairing/Activation, kept
 * independent of but connectable to Licensing (D-048). An installation
 * exists (locally, offline-capable) before it is paired to a company; only
 * pairing binds company_id and activates it for sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_installations', function (Blueprint $table) {
            $table->id();
            $table->uuid('installation_uuid')->unique();
            $table->string('device_key_hash');
            $table->string('name')->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('status')->default(1); // DeviceInstallationStatus: 1=unpaired,2=active,3=revoked
            $table->dateTime('paired_at')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('device_activation_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code_hash')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->foreignId('used_by_installation_id')->nullable()->constrained('device_installations')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_activation_codes');
        Schema::dropIfExists('device_installations');
    }
};
