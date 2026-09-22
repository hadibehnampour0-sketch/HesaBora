<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config as ConfigFacade;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\MariaDbRaceTestCase;
use Tests\Helpers\SeederHelper;

/**
 * Workstream S (Concurrency), Phase 1 -- Settlement & PaymentAllocation
 * deep audit. `PaymentService::allocate()` already locks the PAYMENT row
 * (`Payment::query()->lockForUpdate()->findOrFail()`) before checking its
 * own unallocated balance -- correct, for the payment side. But neither
 * `PaymentService::createPayment()` (direct invoice payment) nor
 * `PaymentService::allocatePayment()` (allocation-based invoice payment)
 * ever locks the INVOICE (or otherwise serializes against another payment/
 * allocation landing on the SAME invoice). The docblock on `allocate()`
 * even says so explicitly: "the caller is responsible for target-side
 * validation."
 *
 * This is a genuine cross-connection race, not a stale-object race like
 * the ones already fixed in Payroll/Invoice-approve/AncillaryCost-approve
 * -- it requires two truly overlapping, uncommitted transactions, which
 * SQLite `:memory:` (every connection is its own private database) cannot
 * reproduce at all. This test runs against a real local MariaDB scratch
 * schema (see MariaDbRaceTestCase) using two independent Laravel
 * connections to the SAME physical database, with the test itself
 * controlling exactly when each "request" reads and commits -- proving
 * the actual database-level behavior a fix would need to change, not a
 * guess about it.
 */
class InvoicePaymentBalanceRaceTest extends MariaDbRaceTestCase
{
    use InvoiceTestHelper, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 500;

    protected ?int $cashBoxSubjectId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;

        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'invoices.update']));
        $this->actingAs($this->user);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        ProductGroup::factory()->withSubjects()->create(['name' => 'g', 'vat' => 0, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'g', 'company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);

        ConfigFacade::set('database.connections.mysql_a', ConfigFacade::get('database.connections.mysql'));
        ConfigFacade::set('database.connections.mysql_b', ConfigFacade::get('database.connections.mysql'));
        DB::purge('mysql_a');
        DB::purge('mysql_b');
    }

    private function cashSubjectId(): int
    {
        if ($this->cashBoxSubjectId !== null) {
            return $this->cashBoxSubjectId;
        }

        $cashBook = Subject::withoutGlobalScopes()->find((int) config('amir.cash_book'));
        $box = Subject::factory()->withParent($cashBook)->create(['name' => 'صندوق', 'company_id' => $this->companyId]);

        return $this->cashBoxSubjectId = (int) $box->id;
    }

    /**
     * Proves the fix: with PaymentService::createPayment() now locking the
     * invoice (Invoice::query()->lockForUpdate()->findOrFail()), a second,
     * genuinely concurrent request for the SAME invoice cannot proceed
     * with a stale balance read -- its own lock acquisition is forced to
     * wait behind the first, real MariaDB row-lock contention, not a
     * simulation. A short session-level `innodb_lock_wait_timeout` on
     * connection B turns that wait into a fast, deterministic assertion
     * instead of a real 50-second stall.
     *
     * Fail-first verified manually: temporarily removing the
     * lockForUpdate() call and rerunning this exact test reproduces the
     * pre-fix bug this test used to fail-first with -- both payments
     * proceed immediately (no blocking) and the invoice ends up overpaid
     * at 1400 against a payable amount of 1000.
     *
     * Methodological note: Eloquent's Model::create()/hydration PINS a
     * model's connection to whatever `config('database.default')` was AT
     * THAT MOMENT (`Builder::newModelInstance()` calls
     * `->setConnection($this->query->getConnection()->getName())`) --
     * later changing `config('database.default')` does NOT repoint an
     * already-hydrated instance. A single $invoice fetched once in setUp
     * and reused for both "sides" would silently keep using its ORIGINAL
     * connection for every relationship query run through it, regardless
     * of which side is "acting". This test fetches a genuinely separate,
     * explicitly-connection-bound Invoice copy for each side
     * (`Invoice::on(...)->find()`) so every query PaymentService runs --
     * not just the lock acquisition itself -- provably happens on the
     * intended physical connection.
     */
    public function test_second_concurrent_payment_request_is_blocked_by_the_invoice_lock_not_left_to_race(): void
    {
        // payable = 2500. An initial, sequential, fully-committed payment
        // of 500 brings the invoice to PARTIALLY_PAID BEFORE the race
        // starts. This matters: PaymentService::syncInvoiceStatus() only
        // calls $invoice->update() when the computed status actually
        // CHANGES, and any UPDATE implicitly takes InnoDB's normal
        // exclusive row lock -- which would incidentally (and
        // misleadingly) serialize the race for reasons having nothing to
        // do with the fix under test, if a race payment pushed the status
        // from APPROVED to PARTIALLY_PAID. Starting already-PARTIALLY_PAID
        // and keeping BOTH race payments within that same status band
        // (neither reaches fully PAID) isolates the invoice-lock fix as
        // the ONLY thing standing between the race and an overpayment.
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 250)], true, ++$this->nextInvoiceNumber);
        $invoiceId = $this->findInvoice(
            $this->sell([$this->productItem($product, 5, 500)], true, ++$this->nextInvoiceNumber)['invoice']->id
        )->id;
        app(PaymentService::class)->createPayment($this->user, Invoice::withoutGlobalScopes()->findOrFail($invoiceId), [
            'amount' => 500,
            'subject_id' => $this->cashSubjectId(),
            'date' => now()->toDateString(),
        ]);

        // Remaining is now 2000. Two payments of 1200 each are each
        // individually valid (and each keeps the invoice PARTIALLY_PAID,
        // not yet PAID), but 1200 + 1200 = 2400 against a remaining
        // balance of 2000 must never both be accepted.
        $paymentData = [
            'amount' => 1200,
            'subject_id' => $this->cashSubjectId(),
            'date' => now()->toDateString(),
        ];

        ConfigFacade::set('database.default', 'mysql_a');
        DB::connection('mysql_a')->beginTransaction();
        $invoiceForA = Invoice::on('mysql_a')->withoutGlobalScopes()->findOrFail($invoiceId);
        app(PaymentService::class)->createPayment($this->user, $invoiceForA, $paymentData);
        // Connection A's payment now exists only inside A's still-open,
        // uncommitted transaction -- invisible to any other connection.

        ConfigFacade::set('database.default', 'mysql_b');
        DB::connection('mysql_b')->statement('SET SESSION innodb_lock_wait_timeout = 2');

        $blocked = false;
        try {
            $invoiceForB = Invoice::on('mysql_b')->withoutGlobalScopes()->findOrFail($invoiceId);
            app(PaymentService::class)->createPayment($this->user, $invoiceForB, $paymentData);
        } catch (\Illuminate\Database\QueryException $e) {
            $blocked = str_contains($e->getMessage(), 'Lock wait timeout');
        } finally {
            ConfigFacade::set('database.default', 'mysql_a');
            DB::connection('mysql_a')->commit();
            ConfigFacade::set('database.default', 'mysql');
            DB::purge('mysql');
        }

        $this->assertTrue($blocked, 'a second concurrent payment request must be blocked by the invoice row lock, not allowed to read a stale balance and proceed');

        $totalPaid = (float) DB::table('payments')->where('invoice_id', $invoiceId)->sum('amount');
        $this->assertSame(1700.0, $totalPaid, 'only the initial 500 plus connection A\'s 1200 should have been recorded; the blocked connection B attempt must not have partially applied');
    }
}
