<?php

namespace Tests\Feature;

use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Support\Facades\Config as ConfigFacade;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\MariaDbRaceTestCase;

/**
 * P6 (API v1 Platform). EnsureIdempotencyKey's correctness depends
 * entirely on the DB-level unique constraint on (key, user_id) -- the
 * middleware itself takes no lock; it relies on the claim-row INSERT
 * itself being the serialization point. This proves that under a real
 * two-connection race (mirroring SettlementObligationRaceTest's own
 * pattern), only ONE of two genuinely concurrent claims for the same key
 * can ever succeed -- the second is blocked by InnoDB's own duplicate-key
 * handling on the uncommitted row, never left to race past it.
 */
class IdempotencyKeyRaceTest extends MariaDbRaceTestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        ConfigFacade::set('database.connections.mysql_a', ConfigFacade::get('database.connections.mysql'));
        ConfigFacade::set('database.connections.mysql_b', ConfigFacade::get('database.connections.mysql'));
        DB::purge('mysql_a');
        DB::purge('mysql_b');
    }

    public function test_second_concurrent_claim_for_the_same_idempotency_key_is_blocked_not_left_to_race(): void
    {
        ConfigFacade::set('database.default', 'mysql_a');
        DB::connection('mysql_a')->beginTransaction();
        IdempotencyKey::on('mysql_a')->create([
            'key' => 'race-key-1',
            'user_id' => $this->user->id,
            'company_id' => null,
            'route_signature' => 'POST api.v1.employees.store',
            'request_fingerprint' => hash('sha256', 'payload-a'),
            'response_status' => null,
            'response_body' => null,
        ]);
        // Connection A's claim row now exists only inside A's still-open,
        // uncommitted transaction -- invisible to any other connection.

        ConfigFacade::set('database.default', 'mysql_b');
        DB::connection('mysql_b')->statement('SET SESSION innodb_lock_wait_timeout = 2');

        $blocked = false;
        try {
            IdempotencyKey::on('mysql_b')->create([
                'key' => 'race-key-1',
                'user_id' => $this->user->id,
                'company_id' => null,
                'route_signature' => 'POST api.v1.employees.store',
                'request_fingerprint' => hash('sha256', 'payload-b'),
                'response_status' => null,
                'response_body' => null,
            ]);
        } catch (\Illuminate\Database\QueryException|\Illuminate\Database\DeadlockException $e) {
            $blocked = str_contains($e->getMessage(), 'Lock wait timeout')
                || str_contains(strtolower($e->getMessage()), 'duplicate');
        } finally {
            ConfigFacade::set('database.default', 'mysql_a');
            DB::connection('mysql_a')->commit();
            ConfigFacade::set('database.default', 'mysql');
            DB::purge('mysql');
        }

        $this->assertTrue($blocked, 'a second concurrent claim for the same idempotency key must be blocked, never allowed to both succeed');

        $count = IdempotencyKey::withoutGlobalScopes()->where('key', 'race-key-1')->where('user_id', $this->user->id)->count();
        $this->assertSame(1, $count, 'exactly one claim row must exist for the key, never two');
    }
}
