<?php

namespace Tests\Feature;

use App\Enums\ChequeType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\User;
use App\Services\ChequeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config as ConfigFacade;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\MariaDbRaceTestCase;
use Tests\Helpers\SeederHelper;

/**
 * Workstream S (Concurrency), Phase 2 -- continuing the full state-
 * transition inventory. `ChequeService::register()`/`update()` both call
 * `validateInvoicePaymentData()` -> `PaymentService::validateInvoicePayment()`
 * -- the exact same balance check `createPayment()`/`allocatePayment()`
 * had before being fixed for CRITICAL finding #7 -- against an
 * `Invoice::findOrFail()` read with NO lock. Two cheques registered
 * against the SAME invoice by two genuinely concurrent requests could
 * each individually pass the remaining-balance check and jointly overpay
 * the invoice, identically to finding #7, just through the cheque
 * registration door instead of the direct-payment door.
 */
class ChequeInvoicePaymentRaceTest extends MariaDbRaceTestCase
{
    use InvoiceTestHelper, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 8800;

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
        $this->seedChequeConfigs();

        ProductGroup::factory()->withSubjects()->create(['name' => 'g', 'vat' => 0, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'g', 'company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);

        ConfigFacade::set('database.connections.mysql_a', ConfigFacade::get('database.connections.mysql'));
        ConfigFacade::set('database.connections.mysql_b', ConfigFacade::get('database.connections.mysql'));
        DB::purge('mysql_a');
        DB::purge('mysql_b');
    }

    private function seedChequeConfigs(): void
    {
        foreach (['cheque_documents_receivable', 'cheque_documents_in_collection', 'cheque_documents_payable'] as $key) {
            $subject = Subject::factory()->create(['company_id' => $this->companyId]);
            config(['amir.'.$key => (string) $subject->id]);
        }
    }

    /**
     * Proves the fix: with ChequeService::register() now locking the
     * invoice before validating/recording the linked payment, a second,
     * genuinely concurrent cheque registration against the SAME invoice
     * cannot proceed with a stale balance read.
     *
     * Fail-first verified manually: temporarily removing the lock and
     * rerunning this exact test reproduces the pre-fix bug -- both
     * registrations proceed immediately (no blocking) and the invoice
     * ends up overpaid.
     */
    public function test_second_concurrent_cheque_registration_against_the_same_invoice_is_blocked_not_left_to_race(): void
    {
        // payable = 2500. An initial, sequential, fully-committed cheque
        // payment of 500 brings the invoice to PARTIALLY_PAID BEFORE the
        // race starts, for the same reason as InvoicePaymentBalanceRaceTest:
        // isolating the fix from PaymentService::syncInvoiceStatus()'s
        // incidental exclusive lock on a status-changing update.
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 250)], true, ++$this->nextInvoiceNumber);
        $invoiceId = $this->findInvoice(
            $this->sell([$this->productItem($product, 5, 500)], true, ++$this->nextInvoiceNumber)['invoice']->id
        )->id;

        app(ChequeService::class)->register($this->user, [
            'direction' => ChequeType::RECEIVABLE->value,
            'purpose' => ChequeType::SETTLEMENT->value,
            'amount' => 500,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'sayad_number' => (string) random_int(1000000000000000, 9999999999999999),
            'customer_id' => $this->customer->id,
            'invoice_id' => $invoiceId,
        ]);

        // Remaining is now 2000. Two cheques of 1200 each are each
        // individually valid (and each keeps the invoice PARTIALLY_PAID),
        // but 1200 + 1200 = 2400 against a remaining balance of 2000 must
        // never both be accepted.
        $chequeData = fn () => [
            'direction' => ChequeType::RECEIVABLE->value,
            'purpose' => ChequeType::SETTLEMENT->value,
            'amount' => 1200,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'sayad_number' => (string) random_int(1000000000000000, 9999999999999999),
            'customer_id' => $this->customer->id,
            'invoice_id' => $invoiceId,
        ];

        ConfigFacade::set('database.default', 'mysql_a');
        DB::connection('mysql_a')->beginTransaction();
        app(ChequeService::class)->register($this->user, $chequeData());
        // Connection A's cheque/payment now exists only inside A's still-
        // open, uncommitted transaction -- invisible to any other connection.

        ConfigFacade::set('database.default', 'mysql_b');
        DB::connection('mysql_b')->statement('SET SESSION innodb_lock_wait_timeout = 2');

        $blocked = false;
        try {
            app(ChequeService::class)->register($this->user, $chequeData());
        } catch (\Illuminate\Database\QueryException|\Illuminate\Database\DeadlockException $e) {
            $blocked = str_contains($e->getMessage(), 'Lock wait timeout');
        } finally {
            ConfigFacade::set('database.default', 'mysql_a');
            DB::connection('mysql_a')->commit();
            ConfigFacade::set('database.default', 'mysql');
            DB::purge('mysql');
        }

        $this->assertTrue($blocked, 'a second concurrent cheque registration against the same invoice must be blocked, not allowed to read a stale balance and proceed');

        $totalPaid = (float) DB::table('payments')->where('invoice_id', $invoiceId)->sum('amount');
        $this->assertSame(1700.0, $totalPaid, 'only the initial 500 plus connection A\'s 1200 cheque should have been recorded');
    }
}
