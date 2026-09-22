<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P7.2 (Segregation of Duties). `employee_id` identifies who the request
 * is ABOUT, not who submitted it (an HR admin can submit on an employee's
 * behalf) -- `created_by` is the actual submitter and the correct
 * "creator" identity for the self-approval check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personnel_requests', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('employee_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('personnel_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
