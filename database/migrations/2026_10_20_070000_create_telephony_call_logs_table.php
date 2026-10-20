<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telephony_call_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('caller_number', 32)->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('channel', 32)->default('voip'); // voip, usb_modem
            $table->string('extension', 32)->nullable();
            $table->string('call_status', 32)->default('RINGING'); // RINGING, ANSWERED, MISSED
            $table->json('customer_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telephony_call_logs');
    }
};
