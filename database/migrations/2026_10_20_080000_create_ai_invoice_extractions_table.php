<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_invoice_extractions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('file_path');
            $table->string('original_filename')->nullable();
            $table->string('file_type', 64)->default('image/jpeg');
            $table->string('status', 32)->default('PENDING_REVIEW')->index(); // PENDING_REVIEW, VERIFIED, REJECTED, CONVERTED
            $table->string('vendor_name')->nullable();
            $table->string('vendor_national_code', 32)->nullable();
            $table->string('vendor_phone', 32)->nullable();
            $table->string('invoice_number', 64)->nullable();
            $table->string('invoice_date', 32)->nullable();
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('vat_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->decimal('confidence_score', 5, 2)->default(0.95);
            $table->json('raw_extracted_data')->nullable();
            $table->unsignedBigInteger('matched_customer_id')->nullable()->index();
            $table->unsignedBigInteger('converted_invoice_id')->nullable()->index();
            $table->timestamp('converted_at')->nullable();
            $table->unsignedBigInteger('reviewer_id')->nullable()->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_invoice_extractions');
    }
};
