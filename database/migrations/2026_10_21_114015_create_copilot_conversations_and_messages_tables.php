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
        Schema::create('copilot_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('گفتگوی مالی جدید');
            $table->timestamps();

            $table->index(['company_id', 'user_id']);
        });

        Schema::create('copilot_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('copilot_conversations')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('user'); // user, assistant, system
            $table->longText('content');
            $table->json('tool_calls')->nullable();
            $table->json('sources')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['company_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('copilot_messages');
        Schema::dropIfExists('copilot_conversations');
    }
};
