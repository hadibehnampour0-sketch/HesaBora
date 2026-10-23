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
        Schema::create('trade_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('title')->default('تبادل و تهاتر کالا با همکار');
            $table->string('status', 32)->default('open'); // open, partially_settled, settled, cancelled
            $table->string('settlement_type', 32)->nullable(); // goods, cash, ledger, invoice
            $table->text('settlement_notes')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'customer_id']);
        });

        Schema::create('trade_exchange_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_exchange_id')->constrained('trade_exchanges')->cascadeOnDelete();
            $table->string('direction', 10)->default('out'); // out (داده شده), in (گرفته شده)
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('item_name');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 32)->default('عدد');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('returned_quantity', 12, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['trade_exchange_id', 'direction']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trade_exchange_items');
        Schema::dropIfExists('trade_exchanges');
    }
};
