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
        Schema::create('pos_terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('psp', 32)->default('behpardakht'); // behpardakht, saman_kish, sadad, iran_kish, asan_pardakht, pasargad, simulator
            $table->string('connection_type', 20)->default('lan'); // lan, serial, bridge
            $table->string('ip_address')->nullable()->default('192.168.1.150');
            $table->unsignedInteger('port')->default(8888);
            $table->string('serial_port', 32)->nullable();
            $table->unsignedInteger('baud_rate')->default(115200);
            $table->string('terminal_id', 32)->nullable();
            $table->string('merchant_id', 32)->nullable();
            $table->foreignId('bank_account_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->string('last_status', 32)->default('unknown'); // online, offline, idle, busy, error
            $table->timestamp('last_ping_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'is_default']);
        });

        Schema::create('pos_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('pos_terminal_id')->constrained('pos_terminals')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('trace_number', 64);
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->default('pending'); // pending, success, failed, cancelled, timeout
            $table->string('rrn', 32)->nullable();
            $table->string('terminal_trace_no', 32)->nullable();
            $table->string('card_number_masked', 32)->nullable();
            $table->string('card_holder_name', 100)->nullable();
            $table->string('response_code', 10)->nullable();
            $table->string('response_message')->nullable();
            $table->json('raw_payload')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('transaction_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['pos_terminal_id', 'status']);
            $table->index(['invoice_id']);
            $table->index(['trace_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_transactions');
        Schema::dropIfExists('pos_terminals');
    }
};
