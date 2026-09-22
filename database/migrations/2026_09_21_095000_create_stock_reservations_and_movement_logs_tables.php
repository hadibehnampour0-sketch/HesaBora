<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4.1-P4.3 (Operational Depth), built as a SEPARATE ADDITIVE LAYER per the
 * owner's explicit choice: this does NOT change when actual stock moves --
 * `ProductService::addProductsQuantities()`/`subProductsQuantities()` and
 * `WarehouseProductStock.quantity` remain the single source of truth for
 * real, on-hand inventory, exactly as they are today (tested exhaustively
 * throughout Workstream S). `stock_reservations` is a parallel, purely
 * informational "earmarked" signal (available = on-hand - active
 * reservations) that nothing in the existing Invoice/ProductService flow
 * reads or is gated by. `goods_receipts`/`delivery_notes` are audit-trail
 * confirmation logs of WHEN goods were physically received/dispatched for
 * an already-existing Invoice -- not a new authoritative stock-moving
 * document.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedTinyInteger('status')->default(1); // 1=active, 2=consumed, 3=released
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'warehouse_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->date('received_date');
            $table->string('reference', 100)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('invoice_id');
        });

        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->date('delivered_date');
            $table->string('reference', 100)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_notes');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('stock_reservations');
    }
};
