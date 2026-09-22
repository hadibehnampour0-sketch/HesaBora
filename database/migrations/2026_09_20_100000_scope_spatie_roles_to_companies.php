<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Organization Authorization & Workflow Governance phase (r12, 2026-09-20).
 *
 * Scopes Spatie role ASSIGNMENTS to a Company via Spatie's "teams" feature,
 * using `company_id` as the team foreign key, while keeping the 6 seeded
 * role DEFINITIONS (Super-Admin, Admin, Accountant, Warehousekeeper, Seller,
 * Employee) shared/global (`roles.company_id IS NULL`). This matches
 * Spatie's own `roles()` relation query, which already OR-matches a NULL
 * role-definition team id against the current team context
 * (vendor/spatie/laravel-permission/src/Traits/HasRoles.php:68) -- only the
 * ASSIGNMENT (pivot) side needs a real company id per row.
 *
 * IMPORTANT interaction with `create_permission_tables.php` (2024_04_18):
 * that migration itself reads `config('permission.teams')` AT MIGRATION RUN
 * TIME, not at the time it was written. Now that `config/permission.php`
 * sets `'teams' => true`, every FRESH install/test run (which always
 * replays every migration from an empty database) has that base migration
 * already create `roles`/`model_has_roles`/`model_has_permissions` WITH the
 * `company_id` column -- using Spatie's own stock shape, where
 * `model_has_roles.company_id`/`model_has_permissions.company_id` are
 * NOT NULL and part of the composite PRIMARY KEY. Only a database that was
 * migrated BEFORE this phase (teams was false at the time) is missing that
 * column and needs this migration's ALTER/rebuild. Every step below is
 * therefore guarded by `Schema::hasColumn(...)`, and produces the identical
 * final shape either way, so the schema never depends on install history:
 *
 *   - `roles.company_id`: nullable. NULL = shared/global role definition,
 *     visible to every company. A real id = a company's own custom role
 *     (see RoleController).
 *   - `model_has_roles.company_id` / `model_has_permissions.company_id`:
 *     NOT NULL, part of the composite primary key (Spatie's own shape).
 *     Company-scoped assignments get their real company id. The Super-Admin
 *     role assignment -- a system-wide oversight grant that must keep
 *     working no matter which company is active (see AppServiceProvider's
 *     Gate::before + User::isSuperAdmin(), which bypasses this table's
 *     team-scoping entirely) -- uses the reserved sentinel `0`, which is
 *     never a real company id (companies use auto-increment ids starting
 *     at 1). NULL cannot be used here: MySQL forbids NULL in a PRIMARY KEY
 *     column, and Spatie's own migration stub agrees (NOT NULL for this
 *     column when teams is enabled).
 *
 * Both engines (SQLite in tests, MySQL/MariaDB in production) are handled
 * identically: SQLite cannot ALTER a table's primary key in place, so the
 * legacy-upgrade path rebuilds both pivot tables via create-new ->
 * copy/expand -> drop-old -> rename, which is also valid, portable SQL on
 * MySQL/MariaDB.
 *
 * Rollback boundary: down() always collapses back to the flat, pre-teams
 * shape (one global row per role/model pair, de-duplicated), regardless of
 * which up() path ran. It does NOT revert `config/permission.php`'s
 * `'teams' => true` -- that is a code change, not a schema change, and must
 * be rolled back deliberately together with this migration, in the same
 * deploy, or the application will query columns that no longer exist.
 */
return new class extends Migration
{
    private const GLOBAL_PERMISSION_NAMES = [
        // Super-Admin-exclusive, cross-company system permissions (see
        // RolesAndPermissionsSeeder::seedRoles(), which excludes these from
        // every other role). A DIRECT grant of one of these must survive
        // regardless of active company, exactly like the Super-Admin role
        // assignment itself.
        'access-super-admin-panel',
        'update-global-configs',
    ];

    /** Sentinel company id for the Super-Admin role assignment: never a real company (companies start at id 1). */
    private const GLOBAL_SENTINEL_COMPANY_ID = 0;

    public function up(): void
    {
        $this->scopeRolesTable();
        $this->rebuildModelHasRoles();
        $this->rebuildModelHasPermissions();
    }

    public function down(): void
    {
        if (Schema::hasColumn('roles', 'company_id')) {
            Schema::table('roles', function (Blueprint $table) {
                if (Schema::getIndexes('roles') && $this->indexExists('roles', 'roles_company_id_name_guard_name_unique')) {
                    $table->dropUnique('roles_company_id_name_guard_name_unique');
                }
                if ($this->indexExists('roles', 'roles_team_foreign_key_index')) {
                    $table->dropIndex('roles_team_foreign_key_index');
                }
                if ($this->foreignKeyExists('roles', 'roles_company_id_foreign')) {
                    $table->dropForeign('roles_company_id_foreign');
                }
                $table->dropColumn('company_id');
                $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
            });
        }

        $this->collapseToFlatPivot('model_has_roles', 'role_id', 'roles');
        $this->collapseToFlatPivot('model_has_permissions', 'permission_id', 'permissions');
    }

    private function scopeRolesTable(): void
    {
        if (! Schema::hasColumn('roles', 'company_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
                $table->index('company_id', 'roles_team_foreign_key_index');
            });

            // Deliberately NO foreign key from roles.company_id to
            // companies.id (matching Spatie's own stock, FK-less shape for
            // this column on a fresh install -- see rebuildModelHasRoles()'s
            // docblock note below on why adding one here is actively unsafe,
            // not just redundant). Application code always sets this from
            // getActiveCompany()/getPermissionsTeamId() (RoleController).
            //
            // SQLite cannot ADD a FOREIGN KEY to an existing table via a
            // plain ALTER TABLE; Laravel's SQLite grammar works around this
            // by transparently rebuilding the whole table (create new,
            // copy rows, drop old, rename). Empirically verified: because
            // model_has_roles.role_id / role_has_permissions.role_id both
            // have onDelete('cascade') back to roles.id, that rebuild's
            // internal "drop old roles table" step cascades and silently
            // WIPES every row in model_has_roles/role_has_permissions --
            // including the very legacy assignment rows this migration
            // exists to preserve and expand. Only a plain ALTER ADD COLUMN
            // (no later ADD CONSTRAINT on this table) avoids that rebuild.
            Schema::table('roles', function (Blueprint $table) {
                $table->dropUnique('roles_name_guard_name_unique');
                $table->unique(['company_id', 'name', 'guard_name'], 'roles_company_id_name_guard_name_unique');
            });
        }
    }

    private function rebuildModelHasRoles(): void
    {
        if (Schema::hasColumn('model_has_roles', 'company_id')) {
            // Fresh install: create_permission_tables.php already produced
            // the final shape (teams was already true when it ran).
            return;
        }

        Schema::create('model_has_roles_new', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_roles_new_model_id_model_type_index');
            $table->unsignedBigInteger('company_id');
            $table->index('company_id', 'model_has_roles_new_team_foreign_key_index');
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['company_id', 'role_id', 'model_id', 'model_type'], 'model_has_roles_new_role_model_type_primary');
        });

        $superAdminRoleIds = DB::table('roles')->where('name', 'Super-Admin')->pluck('id');

        DB::table('model_has_roles')
            ->orderBy('role_id')->orderBy('model_id')
            ->chunk(500, function ($rows) use ($superAdminRoleIds) {
                $insertRows = [];

                foreach ($rows as $row) {
                    if ($row->model_type !== User::class) {
                        // No non-User model ever uses HasRoles in this
                        // codebase today (verified). If one appears later,
                        // fail loudly rather than silently mis-scoping it.
                        throw new \RuntimeException(
                            "Unexpected model_has_roles.model_type '{$row->model_type}' encountered during company-scoping migration; this migration only knows how to scope App\\Models\\User role assignments."
                        );
                    }

                    if ($superAdminRoleIds->contains($row->role_id)) {
                        $insertRows[] = [
                            'company_id' => self::GLOBAL_SENTINEL_COMPANY_ID,
                            'role_id' => $row->role_id,
                            'model_type' => $row->model_type,
                            'model_id' => $row->model_id,
                        ];

                        continue;
                    }

                    $companyIds = DB::table('company_user')->where('user_id', $row->model_id)->pluck('company_id');

                    foreach ($companyIds as $companyId) {
                        $insertRows[] = [
                            'company_id' => $companyId,
                            'role_id' => $row->role_id,
                            'model_type' => $row->model_type,
                            'model_id' => $row->model_id,
                        ];
                    }
                }

                if ($insertRows !== []) {
                    DB::table('model_has_roles_new')->insertOrIgnore($insertRows);
                }
            });

        Schema::drop('model_has_roles');
        Schema::rename('model_has_roles_new', 'model_has_roles');
    }

    private function rebuildModelHasPermissions(): void
    {
        if (Schema::hasColumn('model_has_permissions', 'company_id')) {
            return;
        }

        Schema::create('model_has_permissions_new', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_new_model_id_model_type_index');
            $table->unsignedBigInteger('company_id');
            $table->index('company_id', 'model_has_permissions_new_team_foreign_key_index');
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->primary(['company_id', 'permission_id', 'model_id', 'model_type'], 'model_has_permissions_new_permission_model_type_primary');
        });

        $globalPermissionIds = DB::table('permissions')->whereIn('name', self::GLOBAL_PERMISSION_NAMES)->pluck('id');

        DB::table('model_has_permissions')
            ->orderBy('permission_id')->orderBy('model_id')
            ->chunk(500, function ($rows) use ($globalPermissionIds) {
                $insertRows = [];

                foreach ($rows as $row) {
                    if ($row->model_type !== User::class) {
                        throw new \RuntimeException(
                            "Unexpected model_has_permissions.model_type '{$row->model_type}' encountered during company-scoping migration; this migration only knows how to scope App\\Models\\User direct permission grants."
                        );
                    }

                    if ($globalPermissionIds->contains($row->permission_id)) {
                        $insertRows[] = [
                            'company_id' => self::GLOBAL_SENTINEL_COMPANY_ID,
                            'permission_id' => $row->permission_id,
                            'model_type' => $row->model_type,
                            'model_id' => $row->model_id,
                        ];

                        continue;
                    }

                    $companyIds = DB::table('company_user')->where('user_id', $row->model_id)->pluck('company_id');

                    foreach ($companyIds as $companyId) {
                        $insertRows[] = [
                            'company_id' => $companyId,
                            'permission_id' => $row->permission_id,
                            'model_type' => $row->model_type,
                            'model_id' => $row->model_id,
                        ];
                    }
                }

                if ($insertRows !== []) {
                    DB::table('model_has_permissions_new')->insertOrIgnore($insertRows);
                }
            });

        Schema::drop('model_has_permissions');
        Schema::rename('model_has_permissions_new', 'model_has_permissions');
    }

    private function collapseToFlatPivot(string $table, string $foreignKeyColumn, string $referencedTable): void
    {
        if (! Schema::hasColumn($table, 'company_id')) {
            return;
        }

        $tmpTable = $table.'_flat_rollback';

        Schema::create($tmpTable, function (Blueprint $blueprint) use ($foreignKeyColumn, $referencedTable) {
            $blueprint->unsignedBigInteger($foreignKeyColumn);
            $blueprint->string('model_type');
            $blueprint->unsignedBigInteger('model_id');
            $blueprint->index(['model_id', 'model_type']);
            $blueprint->foreign($foreignKeyColumn)->references('id')->on($referencedTable)->cascadeOnDelete();
            $blueprint->primary([$foreignKeyColumn, 'model_id', 'model_type']);
        });

        DB::table($table)
            ->select($foreignKeyColumn, 'model_type', 'model_id')
            ->distinct()
            ->orderBy($foreignKeyColumn)
            ->chunk(500, function ($rows) use ($tmpTable) {
                DB::table($tmpTable)->insertOrIgnore($rows->map(fn ($row) => (array) $row)->all());
            });

        Schema::drop($table);
        Schema::rename($tmpTable, $table);
    }

    private function indexExists(string $table, string $indexName): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['name'] === $indexName) {
                return true;
            }
        }

        return false;
    }

    private function foreignKeyExists(string $table, string $foreignKeyName): bool
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if ($foreignKey['name'] === $foreignKeyName) {
                return true;
            }
        }

        return false;
    }
};
