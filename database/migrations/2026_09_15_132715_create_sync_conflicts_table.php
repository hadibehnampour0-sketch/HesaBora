<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-072: Conflict Resolution Workflow (Phase 2 stage 2.6). A dedicated
 * record per conflict, separate from the generic sync_inbox staging row it
 * originates from -- this is the reviewable, resolvable, permanently
 * auditable domain object. Both snapshots are kept only to the extent
 * needed for review/resolution; no sensitive data or credentials belong in
 * a snapshot (locked now, matters more once 2.7/2.8 bring in financial
 * entities).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbox_entry_id')->constrained('sync_inbox')->cascadeOnDelete();
            $table->string('entity_type');
            $table->uuid('entity_uuid');
            $table->json('local_snapshot');
            $table->json('server_snapshot');
            $table->unsignedInteger('base_version')->nullable();
            $table->unsignedInteger('server_version');
            $table->foreignId('origin_device_id')->constrained('device_installations')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('status')->default(1); // SyncConflictStatus::OPEN
            $table->unsignedTinyInteger('resolution')->nullable(); // SyncConflictResolution
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->uuid('resulting_operation_id')->nullable(); // set BEFORE the push attempt so a retry reuses it
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['entity_type', 'entity_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_conflicts');
    }
};
