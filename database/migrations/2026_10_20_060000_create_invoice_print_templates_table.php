<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_print_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('name');
            $table->string('paper_size', 32)->default('A4_PORTRAIT'); // A4_PORTRAIT, A4_LANDSCAPE, A5_PORTRAIT, A5_LANDSCAPE, THERMAL_80MM, THERMAL_58MM
            $table->boolean('is_default')->default(false);
            $table->json('header_config')->nullable();
            $table->json('columns_config')->nullable();
            $table->json('footer_config')->nullable();
            $table->string('watermark_text')->nullable();
            $table->text('custom_css')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'paper_size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_print_templates');
    }
};
