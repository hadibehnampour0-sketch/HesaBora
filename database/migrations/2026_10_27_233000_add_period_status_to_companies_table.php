<?php

use App\Enums\PeriodStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('period_status', 32)
                ->default(PeriodStatus::OPEN->value)
                ->after('closed_at');

            $table->timestamp('locked_at')
                ->nullable()
                ->after('period_status');

            $table->foreignId('locked_by')
                ->nullable()
                ->after('locked_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reopened_at')
                ->nullable()
                ->after('locked_by');

            $table->foreignId('reopened_by')
                ->nullable()
                ->after('reopened_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->text('reopen_reason')
                ->nullable()
                ->after('reopened_by');

            $table->index('period_status', 'companies_period_status_idx');
        });

        // Sync initial state for existing records: closed companies become 'closed'
        DB::table('companies')
            ->whereNotNull('closed_at')
            ->update(['period_status' => PeriodStatus::CLOSED->value]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('companies_period_status_idx');
            $table->dropConstrainedForeignId('locked_by');
            $table->dropConstrainedForeignId('reopened_by');
            $table->dropColumn([
                'period_status',
                'locked_at',
                'reopened_at',
                'reopen_reason',
            ]);
        });
    }
};
