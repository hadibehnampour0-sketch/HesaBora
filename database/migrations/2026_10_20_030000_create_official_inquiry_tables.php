<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_inquiry_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('inquiry_type', 32)->index(); // 'shahkar', 'sheba', 'card', 'postal_code'
            $table->string('query_term')->index();
            $table->json('query_payload')->nullable();
            $table->boolean('is_matched')->default(true);
            $table->string('status', 32)->default('success'); // 'success', 'failed', 'rejected'
            $table->json('response_data')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'inquiry_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_inquiry_logs');
    }
};
