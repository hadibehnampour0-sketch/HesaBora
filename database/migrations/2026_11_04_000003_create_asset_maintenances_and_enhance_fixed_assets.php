<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Enhance fixed_assets with practical equipment fields
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->string('category', 60)->default('equipment')->after('name'); // vehicle, equipment, facility, furniture, tools, other
            $table->string('serial_or_plate', 100)->nullable()->after('category');
            $table->string('location', 150)->nullable()->after('serial_or_plate');
            $table->text('description')->nullable()->after('location');
            $table->string('image_path')->nullable()->after('description');
        });

        // 2. Create asset_maintenances (Logbook of repairs, service & operating expenses)
        Schema::create('asset_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180); // e.g. تعویض روغن، تعمیر کولر، سرویس دوره‌ای
            $table->string('maintenance_type', 50)->default('repair'); // service, repair, part_replacement, capital_improvement, inspection_insurance
            $table->date('date');
            $table->decimal('amount', 18, 2);
            $table->foreignId('service_provider_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->unsignedInteger('odometer_or_hours')->nullable();
            $table->text('description')->nullable();
            $table->string('invoice_attachment')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'fixed_asset_id', 'date'], 'asset_maintenances_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_maintenances');

        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropColumn(['category', 'serial_or_plate', 'location', 'description', 'image_path']);
        });
    }
};
