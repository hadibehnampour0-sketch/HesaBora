<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\ProductGroup;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BankReconciliationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config as ConfigFacade;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\MariaDbRaceTestCase;
use Tests\Helpers\SeederHelper;

/**
 * Workstream S (Concurrency), Phase 1 -- broader financial-primitive sweep.
 * `BankReconciliationService::matchLine()` has the exact same structural
 * shape as the Invoice-payment and Document-numbering bugs already fixed
 * this pass: it checks "is this line already reconciled?" and "is this
 * transaction already matched to another line?" via live queries, then
 * writes -- with NO `DB::transaction()` wrapper at all and NO lock.
 * `bank_statement_lines.reconciled_transaction_id` has no unique
 * constraint either.
 *
 * Two DIFFERENT statement lines, matched to the SAME transaction by two
 * genuinely concurrent requests, would both pass the "already used" check
 * and both succeed -- silently double-counting one real bank movement
 * against two separate statement lines, corrupting the reconciliation.
 *
 * Methodological note (see InvoicePaymentBalanceRaceTest for the full
 * explanation): Eloquent pins a model's connection at creation/hydration
 * time to whatever the default connection was AT THAT MOMENT, and later
 * `config('database.default')` changes do not repoint an already-loaded
 * instance. Every model touched by matchLine() (`$line`, `$transaction`)
 * is therefore re-fetched fresh and explicitly bound to the connection
 * for each side of the race, rather than reusing one instance created
 * during setUp.
 */
class BankReconciliationMatchRaceTest extends MariaDbRaceTestCase
{
    use InvoiceTestHelper, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9700;

    protected BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;

        config(['active-company-id' => $this->companyId]);
        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        ProductGroup::factory()->withSubjects()->create(['name' => 'g', 'vat' => 0, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'g', 'company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);

        $bank = new Bank(['name' => 'Test Bank']);
        $bank->company_id = $this->companyId;
        $bank->save();

        $this->bankAccount = BankAccount::factory()->withSubject()->create([
            'company_id' => $this->companyId,
            'bank_id' => $bank->id,
        ]);

        ConfigFacade::set('database.connections.mysql_a', ConfigFacade::get('database.connections.mysql'));
        ConfigFacade::set('database.connections.mysql_b', ConfigFacade::get('database.connections.mysql'));
        DB::purge('mysql_a');
        DB::purge('mysql_b');
    }

    private function approvedSell(float $unit, int $qty): Invoice
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, $qty, $unit * 0.5)], true, ++$this->nextInvoiceNumber);
        $sell = $this->sell([$this->productItem($product, $qty, $unit)], true, ++$this->nextInvoiceNumber)['invoice'];

        return $this->findInvoice($sell->id);
    }

    /**
     * Proves the fix: with matchLine() now locking the transaction row
     * inside a real DB::transaction(), a second, genuinely concurrent
     * match attempt against the SAME transaction cannot proceed with a
     * stale "not yet used" read -- it is forced to wait behind the first,
     * real MariaDB row-lock contention.
     *
     * Fail-first verified manually: temporarily removing the lock/
     * transaction and rerunning this exact test reproduces the pre-fix
     * bug -- both matches proceed immediately (no blocking) and the same
     * transaction ends up linked to two different statement lines.
     */
    public function test_second_concurrent_match_of_the_same_transaction_is_blocked_not_left_to_race(): void
    {
        $invoice = $this->approvedSell(1000, 1);
        $transactionId = Transaction::whereHas('document', fn ($q) => $q->where('documentable_type', Invoice::class)->where('documentable_id', $invoice->id))->firstOrFail()->id;

        $statement = BankStatement::create([
            'bank_account_id' => $this->bankAccount->id,
            'company_id' => $this->companyId,
        ]);
        $lineAId = BankStatementLine::create([
            'bank_statement_id' => $statement->id,
            'bank_account_id' => $this->bankAccount->id,
            'date' => now()->toDateString(),
            'amount' => 1000,
            'description' => 'line A',
            'dedupe_hash' => 'hashA',
        ])->id;
        $lineBId = BankStatementLine::create([
            'bank_statement_id' => $statement->id,
            'bank_account_id' => $this->bankAccount->id,
            'date' => now()->toDateString(),
            'amount' => 1000,
            'description' => 'line B',
            'dedupe_hash' => 'hashB',
        ])->id;

        ConfigFacade::set('database.default', 'mysql_a');
        DB::connection('mysql_a')->beginTransaction();
        $lineAForA = BankStatementLine::on('mysql_a')->findOrFail($lineAId);
        $transactionForA = Transaction::on('mysql_a')->findOrFail($transactionId);
        app(BankReconciliationService::class)->matchLine($lineAForA, $transactionForA, $this->user);
        // Connection A's match now exists only inside A's still-open,
        // uncommitted transaction -- invisible to any other connection.

        ConfigFacade::set('database.default', 'mysql_b');
        DB::connection('mysql_b')->statement('SET SESSION innodb_lock_wait_timeout = 2');

        $blocked = false;
        try {
            $lineBForB = BankStatementLine::on('mysql_b')->findOrFail($lineBId);
            $transactionForB = Transaction::on('mysql_b')->findOrFail($transactionId);
            app(BankReconciliationService::class)->matchLine($lineBForB, $transactionForB, $this->user);
        } catch (\Illuminate\Database\QueryException $e) {
            $blocked = str_contains($e->getMessage(), 'Lock wait timeout');
        } finally {
            ConfigFacade::set('database.default', 'mysql_a');
            DB::connection('mysql_a')->commit();
            ConfigFacade::set('database.default', 'mysql');
            DB::purge('mysql');
        }

        $this->assertTrue($blocked, 'a second concurrent match of the same transaction must be blocked, not allowed to read a stale "not yet used" state and proceed');

        $matchedCount = (int) DB::table('bank_statement_lines')->where('reconciled_transaction_id', $transactionId)->count();
        $this->assertSame(1, $matchedCount, 'the same transaction must never end up matched to more than one statement line');
    }
}
