<?php

namespace Tests\Helpers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

/**
 * Base class for genuine cross-connection concurrency tests.
 *
 * The default test suite runs on SQLite `:memory:` (see phpunit.xml), where
 * every connection is its own private, isolated database -- there is no way
 * to reproduce a real two-transaction race (one connection's uncommitted
 * write being invisible to another connection's read) because there is no
 * shared state to race over in the first place. Proving a `lockForUpdate()`
 * fix actually serializes two overlapping transactions requires a real
 * database with real row-level locking, and requires the fixture data
 * created by the test to be genuinely COMMITTED (not wrapped in the usual
 * RefreshDatabase outer transaction-then-rollback strategy), so a second,
 * independent PDO connection can see it.
 *
 * This repoints the app's default connection at a real local MariaDB
 * instance (the same one this dev environment already has reachable at
 * 127.0.0.1:3306, credentials from .env) using a dedicated, otherwise-empty
 * schema (`test`) so it never touches the real `hesabora` dev database.
 * Migrations run once per test-suite process (tracked via a static flag);
 * each test method gets a fast manual truncate instead of a full
 * re-migration.
 *
 * Subclasses get:
 *  - the app's default Eloquent/service-layer connection pointed at the
 *    scratch MariaDB schema, so calling real service methods (e.g.
 *    PaymentService::allocatePayment()) uses real transactions/locks.
 *  - openSecondConnection(): a raw, independent PDO handle to the SAME
 *    physical database, for manually interleaving a second "concurrent
 *    request" step by step under the test's own control.
 *
 * If MariaDB isn't reachable, tests here skip themselves rather than
 * failing, so this file never breaks a run in an environment without it.
 */
abstract class MariaDbRaceTestCase extends TestCase
{
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_mysql')) {
            $this->markTestSkipped('pdo_mysql extension not available.');
        }

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => '3306',
            'database.connections.mysql.database' => 'test',
            'database.connections.mysql.username' => 'admin',
            'database.connections.mysql.password' => '123456',
        ]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Real MariaDB scratch database not reachable: '.$e->getMessage());
        }

        if (! self::$migrated) {
            Artisan::call('migrate:fresh', ['--force' => true]);
            self::$migrated = true;
        } else {
            $this->truncateAllTables();
        }
    }

    private function truncateAllTables(): void
    {
        $tables = DB::select('SHOW TABLES');
        $key = 'Tables_in_'.config('database.connections.mysql.database');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $row) {
            $table = $row->$key;
            if ($table === 'migrations') {
                continue;
            }
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * A raw, independent PDO connection to the same physical scratch
     * database, for manually driving a second "concurrent request" whose
     * transaction timing this test controls explicitly (BEGIN / a query /
     * COMMIT issued exactly when the test wants them), interleaved against
     * the app's own default connection.
     */
    protected function openSecondConnection(): PDO
    {
        $pdo = new PDO(
            'mysql:host=127.0.0.1;port=3306;dbname=test;charset=utf8mb4',
            'admin',
            '123456',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        return $pdo;
    }
}
