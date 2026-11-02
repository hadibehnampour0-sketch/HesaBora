<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Testing (Workstream T): `throttle` middleware (added to `/login` and
     * `/reset-password` for real rate-limiting protection) counts EVERY
     * request to that route within its window, not just failed ones, and
     * keys by IP -- which is the same fixed test-client "IP" for the
     * entire suite. Without a per-test reset, the cumulative request
     * count across every test file that POSTs to `/login` throughout a
     * full (well under 60 seconds) test run can exceed the limit,
     * intermittently 429-ing unrelated, otherwise-passing tests depending
     * on execution order -- this is not a security issue, it's the test
     * suite needing the same clean-slate guarantee for the cache store
     * that RefreshDatabase already gives the database.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::store()->flush();
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        parent::tearDown();
    }

    /**
     * Intercept test traits to enforce database safety guard before any destructive
     * operations (RefreshDatabase, DatabaseMigrations, DatabaseTransactions, DatabaseTruncation) execute.
     */
    protected function setUpTraits()
    {
        $this->guardDestructiveTestingDatabase();

        return parent::setUpTraits();
    }

    /**
     * Safety Guard: Refuse to execute tests with destructive database operations
     * unless the target database is in-memory/file SQLite or its name explicitly ends
     * with '_test' (e.g. dakhl_test). Under no circumstances will tests ever touch
     * or execute migrations against the development database 'dakhl'.
     */
    protected function guardDestructiveTestingDatabase(): void
    {
        $defaultConnection = config('database.default');
        $databaseName = (string) config("database.connections.{$defaultConnection}.database");

        // In-memory or file-based SQLite test databases are always permitted
        if ($defaultConnection === 'sqlite' || $databaseName === ':memory:') {
            return;
        }

        if (! str_ends_with($databaseName, '_test')) {
            throw new RuntimeException(sprintf(
                "CRITICAL SAFETY VIOLATION: Test suite attempted to run destructive database operations against database '%s' on connection '%s'. Destructive test execution is strictly restricted to databases ending with '_test' (e.g., 'dakhl_test') or in-memory SQLite to protect development data.",
                $databaseName,
                $defaultConnection
            ));
        }
    }
}
