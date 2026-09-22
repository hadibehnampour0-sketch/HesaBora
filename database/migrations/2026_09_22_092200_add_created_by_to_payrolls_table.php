<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P7.2 (Segregation of Duties). Records whoever triggered the payroll
 * calculation (PayrollService::persist()) as the "creator" for the
 * self-approval check on the PendingManagerApproval -> Approved
 * transition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('employee_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
