<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Subject;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Config as ConfigFacade;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\MariaDbRaceTestCase;

/**
 * Workstream S (Concurrency), Phase 2 -- broader financial state/primitive
 * audit. `DocumentService::createDocument()` falls back to
 * `Document::max('number') + 1` whenever no explicit number is given
 * (line ~36) -- and every credit payment and direct invoice payment
 * (`PaymentService::createCreditPayment()` / `paymentDocument()`, both
 * pass `'number' => null`) goes through exactly that fallback on every
 * single call. `documents.number` has no unique constraint at all (see
 * `2024_03_07_112403_create_document_table.php`), so two genuinely
 * concurrent document creations reading the same MAX before either
 * commits do not even collide loudly -- they silently produce two
 * accounting documents with the IDENTICAL reference number.
 *
 * This is the exact same class of bug `AncillaryCostService::
 * nextAncillaryCostNumberForUpdate()` already guards against correctly,
 * for a DIFFERENT numbering sequence, using `lockForUpdate()` on the max
 * row -- `DocumentService::createDocument()` never got the same
 * treatment for the underlying, more foundational Document ledger
 * itself.
 */
class DocumentNumberingRaceTest extends MariaDbRaceTestCase
{
    protected User $user;

    protected int $companyId;

    protected int $subjectAId;

    protected int $subjectBId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;
        config(['active-company-id' => $this->companyId]);

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->subjectAId = (int) Subject::factory()->create(['company_id' => $this->companyId])->id;
        $this->subjectBId = (int) Subject::factory()->create(['company_id' => $this->companyId])->id;

        ConfigFacade::set('database.connections.mysql_a', ConfigFacade::get('database.connections.mysql'));
        ConfigFacade::set('database.connections.mysql_b', ConfigFacade::get('database.connections.mysql'));
        DB::purge('mysql_a');
        DB::purge('mysql_b');
    }

    private function balancedTransactions(): array
    {
        return [
            ['subject_id' => $this->subjectAId, 'desc' => 'race test', 'value' => 100],
            ['subject_id' => $this->subjectBId, 'desc' => 'race test', 'value' => -100],
        ];
    }

    /**
     * Proves the fix: with the number fallback now locking the current-max
     * document row, a second, genuinely concurrent creation cannot read a
     * stale max -- its own lock acquisition is forced to wait behind the
     * first, real MariaDB row-lock contention. A short session-level
     * `innodb_lock_wait_timeout` on connection B turns that wait into a
     * fast, deterministic assertion instead of a real 50-second stall.
     *
     * Fail-first verified manually: temporarily reverting to the bare
     * `Document::max('number') + 1` fallback and rerunning this exact
     * test reproduces the pre-fix bug -- both creations proceed
     * immediately (no blocking) and both documents end up with the
     * identical number.
     */
    public function test_second_concurrent_document_creation_is_blocked_by_the_numbering_lock_not_left_to_race(): void
    {
        $docData = ['date' => now()->toDateString(), 'title' => 'race', 'number' => null];

        ConfigFacade::set('database.default', 'mysql_a');
        DB::connection('mysql_a')->beginTransaction();
        DocumentService::createDocument($this->user, $docData, $this->balancedTransactions());
        // Connection A's document now exists only inside A's still-open,
        // uncommitted transaction -- invisible to any other connection.

        ConfigFacade::set('database.default', 'mysql_b');
        DB::connection('mysql_b')->statement('SET SESSION innodb_lock_wait_timeout = 2');

        $blocked = false;
        try {
            DocumentService::createDocument($this->user, $docData, $this->balancedTransactions());
        } catch (\Illuminate\Database\QueryException $e) {
            $blocked = str_contains($e->getMessage(), 'Lock wait timeout');
        } finally {
            ConfigFacade::set('database.default', 'mysql_a');
            DB::connection('mysql_a')->commit();
            ConfigFacade::set('database.default', 'mysql');
            DB::purge('mysql');
        }

        $this->assertTrue($blocked, 'a second concurrent document creation must be blocked by the numbering lock, not allowed to read a stale max and proceed');

        $documentCount = (int) DB::table('documents')->count();
        $this->assertSame(1, $documentCount, 'only connection A\'s document should have been created; the blocked connection B attempt must not have partially applied');
    }
}
