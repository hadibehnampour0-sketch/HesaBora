<?php

namespace Tests\Unit;

use RuntimeException;
use Tests\TestCase;

class DatabaseSafetyGuardTest extends TestCase
{
    public function test_safety_guard_permits_sqlite_connection(): void
    {
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->guardDestructiveTestingDatabase();
        $this->assertTrue(true);
    }

    public function test_safety_guard_permits_mysql_database_ending_with_test(): void
    {
        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => 'dakhl_test']);

        $this->guardDestructiveTestingDatabase();
        $this->assertTrue(true);
    }

    public function test_safety_guard_blocks_development_database_dakhl(): void
    {
        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => 'dakhl']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("CRITICAL SAFETY VIOLATION: Test suite attempted to run destructive database operations against database 'dakhl'");

        $this->guardDestructiveTestingDatabase();
    }

    public function test_safety_guard_blocks_production_or_arbitrary_database_names(): void
    {
        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => 'hesabora']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("CRITICAL SAFETY VIOLATION: Test suite attempted to run destructive database operations against database 'hesabora'");

        $this->guardDestructiveTestingDatabase();
    }
}
