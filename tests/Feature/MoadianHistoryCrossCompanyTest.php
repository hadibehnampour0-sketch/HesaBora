<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\MoadianHistory;
use App\Models\ProductGroup;
use App\Models\User;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * P9 hardening pass (pre-Tax-OS audit). `MoadianHistory` has no
 * `FiscalYearScope` of its own -- `MoadianHistoryController::index()`
 * queried it directly with zero company filter, leaking every company's
 * tax-submission history (potentially including tax IDs and customer
 * economic codes present in the `data` JSON blob) to any authenticated
 * user regardless of which company was active. Fixed with
 * `whereHas('invoice')` (Invoice's own scope is the only company-deriving
 * relation this table has), mirroring guardrail #36's WarehouseProductStock
 * fix. `show()` was already safe -- it operates on a route-bound `Invoice`,
 * which IS scoped, so this file only needs to prove `index()`.
 */
class MoadianHistoryCrossCompanyTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 8600;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;

        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        ProductGroup::factory()->withSubjects()->create(['name' => 'g', 'vat' => 0, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'g', 'company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);

        $this->user = User::factory()->create();
        Company::find($this->companyId)->users()->syncWithoutDetaching($this->user);
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'invoices.moadian-histories.index']));
        $this->actingAs($this->user);
    }

    private function withActiveCompany(Company $company, \Closure $callback)
    {
        $previous = config('active-company-id');
        config(['active-company-id' => $company->id]);

        try {
            return $callback();
        } finally {
            config(['active-company-id' => $previous]);
        }
    }

    private function myInvoice(): Invoice
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 5, 50)], true, ++$this->nextInvoiceNumber);

        return $this->findInvoice(
            $this->sell([$this->productItem($product, 1, 100)], true, ++$this->nextInvoiceNumber)['invoice']->id
        );
    }

    public function test_moadian_history_index_never_includes_another_companys_history(): void
    {
        $myInvoice = $this->myInvoice();
        MoadianHistory::create(['invoice_id' => $myInvoice->id, 'data' => ['status' => 'SUCCESS', 'referenceNumber' => 'mine-1']]);

        $otherCompany = Company::factory()->create();
        $otherInvoice = $this->withActiveCompany($otherCompany, fn () => Invoice::create([
            'customer_id' => $this->customer->id,
            'number' => 999001,
            'date' => now(),
            'invoice_type' => 1,
            'status' => 3,
            'amount' => 1000,
            'vat' => 0,
        ]));
        MoadianHistory::create(['invoice_id' => $otherInvoice->id, 'data' => ['status' => 'SUCCESS', 'referenceNumber' => 'other-company-secret']]);

        $response = $this->get(route('invoices.moadian-histories.index'));

        $response->assertOk();
        $response->assertDontSee('other-company-secret');

        $viewHistories = $response->viewData('moadianHistories');
        $this->assertTrue($viewHistories->every(fn (MoadianHistory $history) => $history->invoice_id === $myInvoice->id));
    }
}
