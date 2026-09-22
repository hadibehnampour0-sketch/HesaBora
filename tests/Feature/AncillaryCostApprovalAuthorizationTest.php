<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
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
 * Financial Integrity Closure Batch, Section 6 (Authorization hardening):
 * the exact same defect class fixed in InvoiceApprovalAuthorizationTest
 * also existed in AncillaryCostController -- `store()`/`update()` called
 * `auth()->user()->can('ancillary-costs.approve')` and discarded the
 * result, and `changeStatus()` had a bare, unconditional, un-acted-on
 * `can()` call. `ancillary-costs.approve` is a real, separately-seeded
 * permission excluded from the Seller role alongside
 * `ancillary-costs.change-status`. Fixed with the same
 * `abort_unless(...)` convention.
 */
class AncillaryCostApprovalAuthorizationTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9960;

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
    }

    private function actingAsUserWith(array $permissionNames): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(array_map(
            fn (string $name) => Permission::firstOrCreate(['name' => $name]),
            $permissionNames
        ));
        $this->actingAs($user);
        $this->user = $user;

        return $user;
    }

    private function buyInvoiceForAncillaryCost(): Invoice
    {
        $product = $this->createProduct();

        return $this->findInvoice(
            $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber)['invoice']->id
        );
    }

    private function storePayload(Invoice $invoice, int $productId): array
    {
        return [
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'date' => convertToJalali($invoice->date->toDateString(), true),
            'type' => 'Shipping',
            'amount' => 100,
            'vatPrice' => 0,
            'approve' => '1',
            'ancillaryCosts' => [
                ['product_id' => $productId, 'amount' => 100],
            ],
        ];
    }

    public function test_store_with_approve_is_rejected_without_the_approve_permission(): void
    {
        $this->actingAsUserWith(['invoices.ancillary-costs.store']);
        $invoice = $this->buyInvoiceForAncillaryCost();
        $productId = (int) $invoice->items->first()->itemable_id;

        $response = $this->post(route('invoices.ancillary-costs.store', $invoice), $this->storePayload($invoice, $productId));

        $response->assertForbidden();
        $this->assertDatabaseMissing('ancillary_costs', ['invoice_id' => $invoice->id]);
    }

    public function test_store_with_approve_succeeds_with_the_approve_permission(): void
    {
        $this->actingAsUserWith(['invoices.ancillary-costs.store', 'ancillary-costs.approve']);
        $invoice = $this->buyInvoiceForAncillaryCost();
        $productId = (int) $invoice->items->first()->itemable_id;

        $response = $this->post(route('invoices.ancillary-costs.store', $invoice), $this->storePayload($invoice, $productId));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('ancillary_costs', ['invoice_id' => $invoice->id]);
    }

    public function test_change_status_to_approve_is_rejected_without_the_approve_permission(): void
    {
        $this->actingAsUserWith(['invoices.ancillary-costs.store', 'ancillary-costs.approve']);
        $invoice = $this->buyInvoiceForAncillaryCost();
        $productId = (int) $invoice->items->first()->itemable_id;
        $result = \App\Services\AncillaryCostService::createAncillaryCost($this->user, [
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

        // ancillary-costs.change-status (route-level, via `check-permission`
        // middleware) is granted so this request reaches the controller --
        // isolating the controller-level `ancillary-costs.approve` check
        // this test targets from the separate, pre-existing middleware gate.
        $this->actingAsUserWith(['ancillary-costs.change-status']);

        $response = $this->post(route('ancillary-costs.change-status', [$ancillaryCost, 'approve']));

        $response->assertForbidden();
        $this->assertFalse($ancillaryCost->refresh()->status->isApproved());
    }

    public function test_change_status_to_approve_succeeds_with_the_approve_permission(): void
    {
        $this->actingAsUserWith(['invoices.ancillary-costs.store', 'ancillary-costs.approve']);
        $invoice = $this->buyInvoiceForAncillaryCost();
        $productId = (int) $invoice->items->first()->itemable_id;
        $result = \App\Services\AncillaryCostService::createAncillaryCost($this->user, [
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

        $this->actingAsUserWith(['ancillary-costs.change-status', 'ancillary-costs.approve']);

        $response = $this->post(route('ancillary-costs.change-status', [$ancillaryCost, 'approve']));

        $response->assertRedirect();
        $this->assertTrue($ancillaryCost->refresh()->status->isApproved());
    }

    /**
     * Final Marathon Closure Pass: the exact same double-post bug as
     * Invoice's approve/unapprove. A plain resubmit of "approve" against an
     * already-approved ancillary cost silently created a second, orphaned
     * Document -- no concurrency required to reproduce.
     */
    public function test_reapproving_an_already_approved_ancillary_cost_is_a_safe_no_op(): void
    {
        $this->actingAsUserWith(['invoices.ancillary-costs.store', 'ancillary-costs.approve']);
        $invoice = $this->buyInvoiceForAncillaryCost();
        $productId = (int) $invoice->items->first()->itemable_id;
        $result = \App\Services\AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'company_id' => $this->companyId,
            'date' => $invoice->date->toDateString(),
            'type' => 'Shipping',
            'amount' => 100,
            'vatPrice' => 0,
            'ancillaryCosts' => [['product_id' => $productId, 'amount' => 100]],
        ], true);
        $ancillaryCost = $result['ancillaryCost'];
        $documentIdAfterFirstApprove = $ancillaryCost->document_id;

        app(\App\Services\AncillaryCostService::class)->changeAncillaryCostStatus($ancillaryCost->fresh(), 'approve');

        $fresh = $ancillaryCost->fresh();
        $this->assertSame($documentIdAfterFirstApprove, $fresh->document_id, 'Re-approving must not create a second Document.');
        $this->assertNull(
            \App\Models\Document::withoutGlobalScopes()->where('id', '!=', $documentIdAfterFirstApprove)
                ->where('documentable_type', \App\Models\AncillaryCost::class)->where('documentable_id', $ancillaryCost->id)->first(),
            'No orphaned second Document may exist.'
        );
    }
}
