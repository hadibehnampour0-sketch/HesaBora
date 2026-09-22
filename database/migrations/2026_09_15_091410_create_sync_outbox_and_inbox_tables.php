<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-071 decision 7: sync is event/change-based (Outbox -> Sync Batch ->
 * Server Validation -> Accepted/Rejected/Conflict -> Inbox), never a
 * full-database comparison. This migration only lays down the generic
 * queue/log infrastructure (stage 2.4) -- no entity is wired to actually
 * enqueue into these tables yet; that starts with the Customer/Product
 * pilot (stage 2.5).
 *
 * sync_outbox: local changes waiting to be pushed to the counterpart
 * (desktop -> server, or in principle the reverse).
 * sync_inbox: changes received FROM a counterpart, staged here before (and
 * as a permanent record of) being applied -- this is what makes a server
 * rejection a visible, auditable state (D-071 decision 5) instead of a
 * silent rollback: the inbox row itself carries the rejection reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('operation_id')->unique(); // client-generated, stable across retries -- the idempotency key
            $table->string('entity_type');
            $table->uuid('entity_uuid');
            $table->unsignedTinyInteger('operation'); // SyncOperation
            $table->json('payload');
            $table->unsignedInteger('base_sync_version')->nullable(); // the version this change was based on (null for CREATE)
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('status')->default(1); // SyncStatus::PENDING
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['entity_type', 'entity_uuid']);
        });

        Schema::create('sync_inbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('operation_id')->unique(); // matches the outbox operation_id -- push-batch idempotency
            $table->string('entity_type');
            $table->uuid('entity_uuid');
            $table->unsignedTinyInteger('operation'); // SyncOperation
            $table->json('payload');
            $table->unsignedInteger('base_sync_version')->nullable();
            $table->foreignId('origin_device_id')->constrained('device_installations')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('status')->default(1); // SyncStatus::PENDING
            $table->dateTime('applied_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('server_version')->nullable();
            $table->string('local_version')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['entity_type', 'entity_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_inbox');
        Schema::dropIfExists('sync_outbox');
    }
};
