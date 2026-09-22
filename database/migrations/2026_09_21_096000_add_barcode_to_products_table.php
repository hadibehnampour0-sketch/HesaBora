<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4.4 (Barcode support). Purely additive, optional, nullable column --
 * existing products/imports/forms that never set a barcode are completely
 * unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('barcode', 100)->nullable()->after('code');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->unique(['company_id', 'barcode'], 'products_company_barcode_unique');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_company_barcode_unique');
            $table->dropColumn('barcode');
        });
    }
};
