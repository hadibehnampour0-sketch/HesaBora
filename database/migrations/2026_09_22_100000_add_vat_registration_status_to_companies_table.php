<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P9.1 (Tax Profile). `companies` already carries `economical_code`,
 * `national_code`, `moadian_username`, `tax_id`, `certificate_path`,
 * `private_key_path` (flat columns, no separate TaxProfile table) --
 * this is additive to that same existing pattern, not a new model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('vat_registration_status', 20)->nullable()->after('tax_id');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('vat_registration_status');
        });
    }
};
