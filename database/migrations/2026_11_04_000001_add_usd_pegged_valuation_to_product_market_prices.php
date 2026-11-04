<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_market_prices', function (Blueprint $table) {
            $table->decimal('purchase_usd_rate', 18, 4)->nullable()->after('market_price');
            $table->decimal('base_usd_cost', 18, 4)->nullable()->after('purchase_usd_rate');
            $table->decimal('target_margin_percent', 8, 2)->default(20.00)->after('base_usd_cost');
            $table->boolean('is_pegged_to_usd')->default(true)->after('target_margin_percent');
        });
    }

    public function down(): void
    {
        Schema::table('product_market_prices', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_usd_rate',
                'base_usd_cost',
                'target_margin_percent',
                'is_pegged_to_usd',
            ]);
        });
    }
};
