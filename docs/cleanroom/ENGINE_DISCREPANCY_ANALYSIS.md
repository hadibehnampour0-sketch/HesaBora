# SQLite vs MariaDB Test Suite Assertion Discrepancy Analysis
**Date:** 2026-11-02  
**Observed Difference:** SQLite: 8,936 Assertions | MariaDB (`dakhl_test`): 8,937 Assertions  
**Discrepancy:** Exactly +1 Assertion on MariaDB / MySQL  
**Status:** FULLY EXPLAINED & LOCATED  

---

## 1. Root Cause Location
The 1-assertion difference between SQLite and MariaDB is caused by an intentional database-engine conditional branch in:
- **File:** `tests/Feature/WarehouseTest.php`
- **Method:** `test_transfer_locks_stocks_in_deterministic_order_and_rolls_back_on_failure`
- **Lines:** 331–344

```php
// tests/Feature/WarehouseTest.php: lines 331–344

// SQLite has no row-level locking SQL syntax at all (Laravel's SQLite
// grammar compiles lockForUpdate() to an empty string — this is a
// platform limitation, not an application bug), so "for update" only
// ever appears on drivers that actually support it, e.g. MySQL.
$lockingQuery = collect($queries)->first(fn ($sql) => str_contains($sql, 'warehouse_product_stocks')
    && str_contains($sql, 'order by')
    && ! str_contains($sql, 'insert')
    && ! str_contains($sql, 'update warehouse_product_stocks set'));
$this->assertNotNull($lockingQuery);
$this->assertStringContainsString('warehouse_id', $lockingQuery);
$this->assertStringContainsString('order by', $lockingQuery);
if (DB::getDriverName() !== 'sqlite') {
    $this->assertStringContainsString('for update', $lockingQuery);
}
```

---

## 2. Technical Explanation

### SQLite Behavior:
In SQLite, database transactions operate under table-level / file-level locking (`BEGIN IMMEDIATE / EXCLUSIVE`). SQLite does not support `SELECT ... FOR UPDATE` syntax. In Laravel's database query grammar for SQLite (`Illuminate\Database\Query\Grammars\SQLiteGrammar`), calls to `->lockForUpdate()` compile to an empty string. Therefore, testing for the literal string `for update` in captured SQL queries would fail on SQLite.

Line 342 guards against this by checking:
```php
if (DB::getDriverName() !== 'sqlite') {
    $this->assertStringContainsString('for update', $lockingQuery);
}
```
When running on **SQLite**, `DB::getDriverName()` returns `'sqlite'`. The condition evaluates to `false`, and line 343 is skipped.

### MariaDB Behavior:
When running on **MariaDB** (`mysql` connection to `dakhl_test`), `DB::getDriverName()` returns `'mysql'`. The condition evaluates to `true`, and line 343 executes:
```php
$this->assertStringContainsString('for update', $lockingQuery);
```
This executes **exactly one additional assertion** verifying that InnoDB row-level locking (`FOR UPDATE`) was compiled and sent to the database.

---

## 3. Conclusion
The assertion count difference of 1 (8,936 vs 8,937 across 2,184 tests) is not a defect, a skipped test, or an unstable race condition. It is a documented, deterministic engine capability check that verifies pessimistic row-level locking on engines supporting it (MariaDB) while gracefully handling SQLite memory tests.
