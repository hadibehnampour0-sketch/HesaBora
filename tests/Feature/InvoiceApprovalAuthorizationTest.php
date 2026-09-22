<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
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
 * Phase 8 (Authorization + Destructive Actions) finding: `invoices.approve`
 * is a real, separately-seeded permission (deliberately excluded from the
 * Seller role, per RolesAndPermissionsSeeder) meant to gate every
 * approval-equivalent action -- but `InvoiceController::store()`/
 * `update()` called `auth()->user()->can('invoices.approve')` and threw
 * away the boolean result instead of acting on it, so any user who could
 * reach store()/update() at all (i.e. any Seller) could approve an invoice
 * outright just by adding `approve=1` to the request. `changeStatus()` had
 * no permission check of its own either (only a route-level
 * `invoices.change-status` check via the `check-permission` middleware,
 * a different, separately-grantable permission). Fixed with
 * `abort_unless(auth()->user()->can('invoices.approve'), 403)` in all
 * three places -- the same convention already used elsewhere in the
 * codebase (CompanyController, HomeController, Management\UserController).
 */
class InvoiceApprovalAuthorizationTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9970;

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

    private function stockedProduct(): \App\Models\Product
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 50)], true, ++$this->nextInvoiceNumber);

        return $this->findProduct($product->id);
    }

    private function storePayload(\App\Models\Product $product, int $number): array
    {
        $warehouseId = $product->warehouseStocks()->orderBy('warehouse_id')->value('warehouse_id');

        return [
            'title' => 'Sell invoice',
            'date' => convertToJalali(now()->toDateString(), true),
            'invoice_type' => InvoiceType::SELL->valueName(),
            'customer_id' => $this->customer->id,
            'warehouse_id' => $warehouseId,
            'document_number' => $number,
            'invoice_number' => $number,
            'approve' => '1',
            'transactions' => [[
                'item_id' => 'product-'.$product->id,
                'quantity' => 1,
                'unit' => 100,
                'total' => 100,
                'off' => 0,
                'vat' => 0,
            ]],
        ];
    }

    public function test_store_with_approve_is_rejected_without_the_approve_permission(): void
    {
        $this->actingAsUserWith(['invoices.store']);
        $product = $this->stockedProduct();
        $number = ++$this->nextInvoiceNumber;

        $response = $this->post(route('invoices.store'), $this->storePayload($product, $number));

        $response->assertForbidden();
        $this->assertDatabaseMissing('invoices', ['number' => $number, 'company_id' => $this->companyId]);
    }

    public function test_store_with_approve_succeeds_with_the_approve_permission(): void
    {
        $this->actingAsUserWith(['invoices.store', 'invoices.approve']);
        $product = $this->stockedProduct();
        $number = ++$this->nextInvoiceNumber;

        $response = $this->post(route('invoices.store'), $this->storePayload($product, $number));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('invoices', ['number' => $number, 'company_id' => $this->companyId]);
    }

    public function test_update_with_approve_is_rejected_without_the_approve_permission(): void
    {
        $this->actingAsUserWith(['invoices.store']);
        $product = $this->stockedProduct();
        $draftNumber = ++$this->nextInvoiceNumber;
        $draft = $this->findInvoice(
            $this->sell([$this->productItem($product, 1, 100)], false, $draftNumber)['invoice']->id
        );

        $this->actingAsUserWith(['invoices.update']);

        $response = $this->put(route('invoices.update', $draft), array_merge(
            $this->storePayload($product, $draftNumber),
            ['invoice_id' => $draft->id]
        ));

        $response->assertForbidden();
        $this->assertFalse($this->findInvoice($draft->id)->status->isApprovedOrSettled());
    }

    public function test_update_with_approve_succeeds_with_the_approve_permission(): void
    {
        $this->actingAsUserWith(['invoices.store']);
        $product = $this->stockedProduct();
        $draftNumber = ++$this->nextInvoiceNumber;
        $draft = $this->findInvoice(
            $this->sell([$this->productItem($product, 1, 100)], false, $draftNumber)['invoice']->id
        );
        app(\App\Services\InvoiceService::class)->changeInvoiceStatus($draft, 'ready_to_approve');
        $draft = $this->findInvoice($draft->id);

        $this->actingAsUserWith(['invoices.update', 'invoices.approve']);

        $response = $this->put(route('invoices.update', $draft), array_merge(
            $this->storePayload($product, $draftNumber),
            ['invoice_id' => $draft->id]
        ));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        // A SELL invoice's updateInvoice(..., approved: true) always lands on
        // READY_TO_APPROVE (never APPROVED directly) -- pre-existing,
        // unrelated behavior; what this test proves is that the request
        // reached that code path at all, which the missing-permission
        // variant above proves it cannot.
        $this->assertTrue($this->findInvoice($draft->id)->status->isReadyToApprove());
    }

    private function readyToApproveDraft(\App\Models\Product $product): Invoice
    {
        $draft = $this->findInvoice(
            $this->sell([$this->productItem($product, 1, 100)], false, ++$this->nextInvoiceNumber)['invoice']->id
        );
        app(\App\Services\InvoiceService::class)->changeInvoiceStatus($draft, 'ready_to_approve');

        return $this->findInvoice($draft->id);
    }

    public function test_change_status_to_approved_is_rejected_without_the_approve_permission(): void
    {
        $this->actingAsUserWith(['invoices.store', 'invoices.change-status']);
        $product = $this->stockedProduct();
        $draft = $this->readyToApproveDraft($product);

        // invoices.change-status (route-level, via `check-permission`
        // middleware) is granted so this request reaches the controller --
        // isolating the controller-level `invoices.approve` check this test
        // targets from the separate, pre-existing middleware gate.
        $this->actingAsUserWith(['invoices.change-status']);

        $response = $this->post(route('invoices.change-status', [$draft, 'approved']));

        $response->assertForbidden();
        $this->assertFalse($this->findInvoice($draft->id)->status->isApprovedOrSettled());
    }

    public function test_change_status_to_approved_succeeds_with_the_approve_permission(): void
    {
        $this->actingAsUserWith(['invoices.store', 'invoices.change-status']);
        $product = $this->stockedProduct();
        $draft = $this->readyToApproveDraft($product);

        $this->actingAsUserWith(['invoices.change-status', 'invoices.approve']);

        $response = $this->post(route('invoices.change-status', [$draft, 'approved']));

        $response->assertRedirect();
        $this->assertTrue($this->findInvoice($draft->id)->status->isApprovedOrSettled());
    }
}
