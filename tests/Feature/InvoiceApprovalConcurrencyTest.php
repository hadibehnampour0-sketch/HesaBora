<?php

namespace Tests\Feature;

use App\Models\AncillaryCost;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\AncillaryCostService;
use App\Services\InvoiceService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * Organization Authorization & Workflow Governance phase, Workstream S
 * (Concurrency). The Financial Foundation phase's guardrail #16 already
 * closed the SEQUENTIAL double-approve defect (`InvoiceService::
 * userFacingApproveInvoice()`/`unapproveInvoice()` and
 * `AncillaryCostService::toggleInvoiceApproval()` now check current status
 * first and no-op if already there). That guard runs on whatever instance
 * the caller passed in, with no `lockForUpdate()` anywhere in either
 * `changeInvoiceStatus()`'s or `changeAncillaryCostStatus()`'s call chain.
 *
 * This tests the CONCURRENT variant of the exact same bug class in both
 * services: two requests that both bind the record while it is still
 * unapproved, before either one's approve() call commits, would both read
 * "not yet approved" and both run the approval mechanics -- which create a
 * new `Document`, mutate warehouse quantities, and recalculate average
 * cost. A doubled call here is not a cosmetic duplicate audit row (as it
 * was for Payroll) -- it is a second phantom Document and double-counted
 * inventory/COG movement, the exact disaster class the sequential guard
 * was built to prevent, just reached through a different door.
 */
class InvoiceApprovalConcurrencyTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9950;

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

        ProductGroup::factory()->withSubjects()->create(['name' => 'g', 'vat' => 10, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'g', 'company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);
    }

    public function test_concurrent_approve_calls_do_not_create_a_duplicate_document_or_double_post_inventory(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 500)], true, ++$this->nextInvoiceNumber);

        $unapprovedInvoice = $this->findInvoice(
            $this->sell([$this->productItem($product, 2, 1000)], false, ++$this->nextInvoiceNumber)['invoice']->id
        );

        $preApprovalQuantity = (float) $product->fresh()->quantity;

        // Two "requests" both bind the same unapproved Invoice before
        // either one's approve() call commits -- simulating two genuinely
        // concurrent HTTP requests that both read the pre-write row.
        // Calling the service directly (bypassing route-model-binding's
        // own fresh-per-request read) is how this is reproduced
        // deterministically in a single process.
        $racingInstanceA = $this->findInvoice($unapprovedInvoice->id);
        $racingInstanceB = $this->findInvoice($unapprovedInvoice->id);

        app(InvoiceService::class)->changeInvoiceStatus($racingInstanceA, 'approved');

        // Without a lock, $racingInstanceB is still a stale not-approved
        // read -- exactly what a second concurrent request's model would
        // be -- and would independently pass the isApprovedOrSettled()
        // guard and run approveInvoice() a second time.
        app(InvoiceService::class)->changeInvoiceStatus($racingInstanceB, 'approved');

        $finalInvoice = $this->findInvoice($unapprovedInvoice->id);
        $this->assertTrue($finalInvoice->status->isApproved());

        $documentsForInvoice = Document::withoutGlobalScopes()
            ->where('documentable_type', Invoice::class)
            ->where('documentable_id', $finalInvoice->id)
            ->count();

        $this->assertSame(1, $documentsForInvoice, 'a raced double-approve must not create a second phantom Document for the same invoice');

        $expectedQuantity = $preApprovalQuantity - 2;
        $this->assertEquals($expectedQuantity, (float) $product->fresh()->quantity, 'a raced double-approve must not double-deduct warehouse quantity');
    }

    public function test_concurrent_ancillary_cost_approve_calls_do_not_create_a_duplicate_document(): void
    {
        $product = $this->createProduct();
        $invoice = $this->findInvoice(
            $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber)['invoice']->id
        );
        $productId = (int) $invoice->items->first()->itemable_id;

        $result = AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'company_id' => $this->companyId,
            'date' => $invoice->date->toDateString(),
            'type' => 'Shipping',
            'amount' => 100,
            'vatPrice' => 0,
            'ancillaryCosts' => [['product_id' => $productId, 'amount' => 100]],
        ], false);
        $ancillaryCost = $result['ancillaryCost'];

        // Two "requests" both bind the same unapproved AncillaryCost before
        // either one's approve() call commits.
        $racingInstanceA = $ancillaryCost->fresh();
        $racingInstanceB = $ancillaryCost->fresh();

        app(AncillaryCostService::class)->changeAncillaryCostStatus($racingInstanceA, 'approve');

        // Without a lock, $racingInstanceB is still a stale not-approved
        // read and would independently pass the isApproved() guard and run
        // toggleInvoiceApproval() a second time.
        app(AncillaryCostService::class)->changeAncillaryCostStatus($racingInstanceB, 'approve');

        $fresh = $ancillaryCost->fresh();
        $this->assertTrue($fresh->status->isApproved());

        $documentsForAncillaryCost = Document::withoutGlobalScopes()
            ->where('documentable_type', AncillaryCost::class)
            ->where('documentable_id', $fresh->id)
            ->count();

        $this->assertSame(1, $documentsForAncillaryCost, 'a raced double-approve must not create a second phantom Document for the same ancillary cost');
    }
}
