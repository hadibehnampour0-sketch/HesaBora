<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * D-071 decision 2: canonical UUID is scoped to entities that genuinely need
 * distributed/cross-device identity -- not a blanket migration. The UUID is
 * layered on top of each table's existing auto-increment `id`, never
 * replacing it, so this migration is purely additive (new nullable columns,
 * backfilled, then made unique) and carries none of the risk of a primary-key
 * change.
 *
 * Every listed table also gets the shared sync envelope fields from the
 * D-071 Sync Contract: sync_version (bumped on every local mutation),
 * sync_status (PENDING/SYNCED/CONFLICT/REJECTED -- see App\Enums\SyncStatus),
 * and origin_device_id (null = created directly on the server/web, non-null
 * = created by that paired device). None of this wires up actual sync
 * activity yet (that starts at 2.4/2.5) -- this migration only lays the
 * schema foundation all later stages build on.
 */
return new class extends Migration
{
    private const TABLES = ['customers', 'products', 'documents', 'invoices', 'payments', 'transactions', 'subjects'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->after('id');
                $table->unsignedInteger('sync_version')->default(1);
                $table->unsignedTinyInteger('sync_status')->default(2); // SyncStatus::SYNCED
                $table->foreignId('origin_device_id')->nullable()->constrained('device_installations')->nullOnDelete();
            });
        }

        foreach (self::TABLES as $table) {
            DB::table($table)->whereNull('uuid')->orderBy('id')->chunkById(500, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
                }
            });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unique('uuid');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropUnique([$table === 'transactions' ? 'transactions_uuid_unique' : $table.'_uuid_unique']);
                $blueprint->dropConstrainedForeignId('origin_device_id');
                $blueprint->dropColumn(['uuid', 'sync_version', 'sync_status']);
            });
        }
    }
};
