<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SettlementObligation;
use App\Models\User;
use App\Services\SettlementService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config as ConfigFacade;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\MariaDbRaceTestCase;

/**
 * Epic Pack 3 (P3.2 Payment Terms). SettlementService::createObligation()
 * (the private helper both createObligationForCheque() and
 * createObligationForInvoice() share) used to check-then-create with no
 * lock at all -- two genuinely concurrent calls for the SAME source could
 * each pass the exists() check before either commits, silently doubling
 * the obligation (and, via applyToInvoice(), doubling the scheduled
 * installment total for an invoice that is only actually owed once).
 * Fixed by locking the source row (Cheque/Invoice, which always already
 * exists) before checking, plus a DB-level unique constraint on
 * [source_type, source_id] as the final collision guard.
 */
class SettlementObligationRaceTest extends MariaDbRaceTestCase
{
    protected User $user;

    protected Customer $customer;

    protected int $companyId;

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

        $this->customer = Customer::factory()->create(['company_id' => $this->companyId]);

        ConfigFacade::set('database.connections.mysql_a', ConfigFacade::get('database.connections.mysql'));
        ConfigFacade::set('database.connections.mysql_b', ConfigFacade::get('database.connections.mysql'));
        DB::purge('mysql_a');
        DB::purge('mysql_b');
    }

    /**
     * Fail-first verified manually: temporarily reverting createObligation()
     * to a plain exists()-then-create() (no lock, no DB::transaction())
     * reproduces this exact failure -- both connections succeed, and a
     * second SettlementObligation row is created for the same invoice.
     */
    public function test_second_concurrent_obligation_creation_for_the_same_invoice_is_blocked_not_left_to_race(): void
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'number' => 1001,
            'date' => now(),
            'invoice_type' => 1,
            'status' => 1,
            'amount' => 1000,
            'vat' => 0,
        ]);

        ConfigFacade::set('database.default', 'mysql_a');
        DB::connection('mysql_a')->beginTransaction();
        app(SettlementService::class)->createObligationForInvoice(Invoice::on('mysql_a')->find($invoice->id), $this->user);
        // Connection A's obligation now exists only inside A's still-open,
        // uncommitted transaction -- invisible to any other connection.

        ConfigFacade::set('database.default', 'mysql_b');
        DB::connection('mysql_b')->statement('SET SESSION innodb_lock_wait_timeout = 2');

        $blocked = false;
        try {
            app(SettlementService::class)->createObligationForInvoice(Invoice::on('mysql_b')->find($invoice->id), $this->user);
        } catch (\Illuminate\Database\QueryException|\Illuminate\Database\DeadlockException $e) {
            $blocked = str_contains($e->getMessage(), 'Lock wait timeout');
        } finally {
            ConfigFacade::set('database.default', 'mysql_a');
            DB::connection('mysql_a')->commit();
            ConfigFacade::set('database.default', 'mysql');
            DB::purge('mysql');
        }

        $this->assertTrue($blocked, 'a second concurrent obligation-creation attempt for the same source must be blocked, not allowed to race past the exists() check');

        $count = SettlementObligation::withoutGlobalScopes()
            ->where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->count();
        $this->assertSame(1, $count, 'exactly one obligation must exist for the invoice, never two');
    }
}
