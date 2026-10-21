<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('market_quotes');
        Schema::dropIfExists('product_market_prices');

        Schema::create('market_quotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('symbol', 32)->index();
            $table->string('title_fa', 100);
            $table->string('title_en', 100)->nullable();
            $table->string('category', 20)->index(); // currency, gold, coin, crypto
            $table->decimal('price', 18, 4);
            $table->decimal('previous_price', 18, 4)->nullable();
            $table->decimal('change_amount', 18, 4)->default(0);
            $table->decimal('change_percent', 8, 2)->default(0);
            $table->decimal('high_price', 18, 4)->nullable();
            $table->decimal('low_price', 18, 4)->nullable();
            $table->string('source', 20)->default('online'); // online, manual
            $table->unsignedBigInteger('recorded_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['category', 'symbol'], 'mq_cat_sym_idx');
        });

        Schema::create('product_market_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('company_id')->index();
            $table->decimal('market_price', 18, 4)->nullable();
            $table->string('market_source_type', 20)->default('manual'); // manual, web_link, guild_peer
            $table->string('market_source_url', 500)->nullable();
            $table->boolean('is_wholesale_broadcast')->default(false)->index();
            $table->decimal('wholesale_price', 18, 4)->nullable();
            $table->decimal('wholesale_min_qty', 12, 2)->default(1);
            $table->string('wholesale_note', 255)->nullable();
            $table->string('guild_category', 50)->nullable()->index();
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'company_id'], 'pmp_prod_comp_uniq');
            $table->index(['guild_category', 'is_wholesale_broadcast'], 'pmp_guild_broadcast_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_market_prices');
        Schema::dropIfExists('market_quotes');
    }
};
