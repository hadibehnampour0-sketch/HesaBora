<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-085: the smallest schema change that can distinguish a Document
 * representing this Company's own locally-originated financial activity
 * (NATIVE) from one copied/imported as baseline history from another
 * fiscal year (BASELINE). NOT NULL with a NATIVE default so every existing
 * row is safely, automatically classified as blocking Company deletion --
 * no legacy-data migration/guess is required or performed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedTinyInteger('provenance')->default(1)->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('provenance');
        });
    }
};
