<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P9.6 (Evidence Vault). Polymorphic so it can attach to any financial
 * record (AncillaryCost today, extensible to Invoice/MoadianHistory
 * later) without a new table per document type. Stored on the `local`
 * disk (storage/app/, never web-exposed -- unlike `public`, which
 * DocumentFileService uses for ordinary document attachments) since tax
 * evidence should never be reachable by a guessable direct URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('evidenceable_type');
            $table->unsignedBigInteger('evidenceable_id');
            $table->string('document_type', 40);
            $table->string('title');
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type', 100);
            $table->string('file_hash', 64);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();

            // Guardrail #33: name explicitly rather than trust the
            // auto-generated name.
            $table->index(['company_id', 'evidenceable_type', 'evidenceable_id'], 'tax_evidences_lookup_index');
            $table->index(['company_id', 'document_type'], 'tax_evidences_doc_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_evidences');
    }
};
