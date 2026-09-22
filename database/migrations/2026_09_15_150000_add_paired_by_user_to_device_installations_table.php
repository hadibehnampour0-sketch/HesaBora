<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-076 -- a real gap 2.7D exposes: approving/posting an invoice through
 * the EXISTING accounting code (InvoiceService::approveInvoice()) needs a
 * real acting user to attribute the resulting Document to (it reads
 * auth()->user() internally). A device has no logged-in web session, and
 * DeviceInstallation previously carried no durable "which user does this
 * device act as" reference -- only the one-time DeviceActivationCode
 * `created_by`, discarded after pairing. This column keeps that fact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_installations', function (Blueprint $table) {
            $table->foreignId('paired_by_user_id')->nullable()->after('company_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('device_installations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paired_by_user_id');
        });
    }
};
