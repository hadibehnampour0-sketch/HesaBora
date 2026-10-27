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
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('reverses_document_id')
                ->nullable()
                ->after('approver_id')
                ->constrained('documents')
                ->nullOnDelete();

            $table->timestamp('reversed_at')
                ->nullable()
                ->after('reverses_document_id')
                ->index();

            $table->foreignId('reversed_by_id')
                ->nullable()
                ->after('reversed_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('reversal_reason_code', 64)
                ->nullable()
                ->after('reversed_by_id');

            $table->text('reversal_reason')
                ->nullable()
                ->after('reversal_reason_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reverses_document_id');
            $table->dropConstrainedForeignId('reversed_by_id');
            $table->dropColumn([
                'reversed_at',
                'reversal_reason_code',
                'reversal_reason',
            ]);
        });
    }
};
