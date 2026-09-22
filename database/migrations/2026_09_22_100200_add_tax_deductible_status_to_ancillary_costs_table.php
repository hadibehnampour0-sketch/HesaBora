<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ancillary_costs', function (Blueprint $table) {
            $table->string('tax_deductible_status', 20)->nullable()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('ancillary_costs', function (Blueprint $table) {
            $table->dropColumn('tax_deductible_status');
        });
    }
};
