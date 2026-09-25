<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integration Hub skeleton (design doc Part B). Two tables only: a shared external-reference
 * map (also the basis for the future Migration platform) and an idempotent event ledger.
 * Neither table holds accounting data; handlers that turn events into invoices/payments must
 * go through InvoiceService/PaymentService (guardrails D-084/D-086..D-089).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('source_system', 40);
            $table->string('external_id', 120);
            $table->string('entity_type', 60);
            $table->unsignedBigInteger('entity_id');
            $table->timestamps();

            $table->unique(['company_id', 'source_system', 'external_id', 'entity_type'], 'ext_refs_company_source_ext_entity_unique');
            $table->index(['company_id', 'entity_type', 'entity_id'], 'ext_refs_company_entity_idx');
        });

        Schema::create('integration_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('source_system', 40);
            $table->string('external_id', 120);
            $table->string('event_type', 40);
            $table->string('payload_hash', 64);
            $table->json('payload');
            $table->string('status', 24);
            $table->text('message')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'source_system', 'external_id', 'event_type'], 'int_events_company_source_ext_event_unique');
            $table->index(['company_id', 'status'], 'int_events_company_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_events');
        Schema::dropIfExists('external_references');
    }
};
