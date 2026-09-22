<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('route_signature');
            $table->string('request_fingerprint', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamps();

            // Guardrail #33: MariaDB's 64-char identifier cap -- name explicitly
            // rather than trusting the auto-generated name on these two columns.
            $table->unique(['key', 'user_id'], 'idempotency_keys_key_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
