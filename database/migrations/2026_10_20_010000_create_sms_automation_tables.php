<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32)->default('log'); // log, kavenegar, melipayamak, farazsms
            $table->text('api_key')->nullable();
            $table->string('sender_number', 64)->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('auto_invoice_approved')->default(true);
            $table->boolean('auto_payment_received')->default(true);
            $table->boolean('auto_cheque_due_reminder')->default(true);
            $table->boolean('auto_debt_reminder')->default(false);
            $table->timestamps();

            $table->unique('company_id');
        });

        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 64); // invoice_approved, payment_received, cheque_due, debt_reminder
            $table->string('pattern_code', 64)->nullable();
            $table->text('template_text');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'event_type']);
        });

        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('recipient', 32);
            $table->string('event_type', 64)->nullable();
            $table->text('message');
            $table->string('provider', 32)->default('log');
            $table->string('status', 32)->default('pending'); // pending, sent, failed
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['recipient', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
        Schema::dropIfExists('sms_templates');
        Schema::dropIfExists('sms_configurations');
    }
};
