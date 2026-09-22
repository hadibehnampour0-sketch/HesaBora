<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4.5/P4.6 (Lot/Batch tracking, Serial numbers). Same additive-layer
 * principle as P4.1-P4.3: neither table is read by or gates
 * ProductService's existing quantity logic -- both are informational
 * sub-unit tracking on top of the aggregate WarehouseProductStock.quantity,
 * which remains the single source of truth for how much is actually on
 * hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('lot_number', 100);
            $table->decimal('quantity', 18, 4);
            $table->date('received_date');
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'product_id', 'lot_number'], 'product_lots_company_product_lot_unique');
        });

        Schema::create('product_serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial_number', 150);
            $table->unsignedTinyInteger('status')->default(1); // 1=in_stock, 2=sold
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'serial_number'], 'product_serials_company_serial_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_serial_numbers');
        Schema::dropIfExists('product_lots');
    }
};
