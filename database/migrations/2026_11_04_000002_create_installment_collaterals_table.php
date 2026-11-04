<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add interest rate and method tracking to installment_contracts
        Schema::table('installment_contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('installment_contracts', 'calculation_method')) {
                $table->string('calculation_method', 32)->default('MARKET_MONTHLY')->after('plan_id');
            }
            if (! Schema::hasColumn('installment_contracts', 'applied_interest_rate')) {
                $table->decimal('applied_interest_rate', 5, 2)->default(3.00)->after('calculation_method');
            }
        });

        // Create installment_collaterals table
        Schema::create('installment_collaterals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('contract_id')->index();
            $table->string('type', 32)->index(); // CHEQUE, GOLD, PROPERTY, VEHICLE, PROMISSORY_NOTE, OTHER
            $table->string('title');
            $table->string('purpose', 32)->default('GUARANTEE'); // GUARANTEE, PAYMENT
            $table->bigInteger('estimated_value')->nullable(); // Estimated value in Rials
            $table->string('status', 32)->default('RECEIVED'); // RECEIVED, RELEASED, EXECUTED

            // Cheque specific fields
            $table->string('cheque_sayad_number', 32)->nullable();
            $table->string('cheque_bank', 128)->nullable();
            $table->string('cheque_account_owner', 128)->nullable();
            $table->date('cheque_due_date')->nullable();
            $table->bigInteger('cheque_amount')->nullable();
            $table->unsignedInteger('cheque_installment_number')->nullable();

            // Gold specific fields
            $table->decimal('gold_weight_grams', 8, 3)->nullable(); // e.g. 15.350 grams
            $table->unsignedSmallInteger('gold_karat')->nullable()->default(18); // 18, 24, etc.

            // Property specific fields
            $table->string('property_registry_code', 128)->nullable();
            $table->text('property_address')->nullable();
            $table->decimal('property_area', 10, 2)->nullable();

            // Vehicle specific fields
            $table->string('vehicle_model', 128)->nullable();
            $table->string('vehicle_plate', 64)->nullable();
            $table->string('vehicle_chassis_number', 128)->nullable();
            $table->string('vehicle_color', 64)->nullable();

            // Promissory note specific fields
            $table->string('promissory_note_number', 64)->nullable();

            // Common attachment and metadata
            $table->string('attachment_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->date('received_at')->nullable();
            $table->date('released_at')->nullable();
            $table->text('release_notes')->nullable();
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('installment_contracts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_collaterals');

        Schema::table('installment_contracts', function (Blueprint $table) {
            if (Schema::hasColumn('installment_contracts', 'applied_interest_rate')) {
                $table->dropColumn('applied_interest_rate');
            }
            if (Schema::hasColumn('installment_contracts', 'calculation_method')) {
                $table->dropColumn('calculation_method');
            }
        });
    }
};
