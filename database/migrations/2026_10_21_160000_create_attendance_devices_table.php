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
        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('brand', 32)->default('zkteco'); // zkteco, kara2000, palizafzar, virdi, suprema, generic
            $table->string('protocol', 32)->default('zk_tcp'); // zk_tcp, zk_udp, adms_push, rest_api
            $table->string('ip_address')->default('192.168.1.201');
            $table->unsignedInteger('port')->default(4370);
            $table->string('comm_key')->default('0');
            $table->string('serial_number')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_sync')->default(true);
            $table->string('cloud_push_token', 64)->unique();
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_status', 32)->default('unknown'); // online, offline, synced, error
            $table->text('last_status_message')->nullable();
            $table->unsignedInteger('last_log_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_devices');
    }
};
