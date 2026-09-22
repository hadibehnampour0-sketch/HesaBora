<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\WorkflowGovernanceSettingsService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * P7.1 (Approval Chains). Owner's chosen design: a per-company amount
 * threshold (WorkflowGovernanceSettingsService::invoiceApprovalChainThreshold(),
 * null/disabled by default) that, when an invoice's amount is at/above it,
 * requires a SECOND, independent approval (a separate `invoices.final-approve`
 * permission, and a different user than whoever did the first step) before
 * the invoice actually posts. Below threshold -- or when no threshold is
 * configured at all -- every invoice takes exactly the same single-step
 * path this app has always had.
 */
class InvoiceApprovalChainTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 8700;

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

    private function setThreshold(?float $threshold): void
    {
        app(WorkflowGovernanceSettingsService::class)->update($this->companyId, false, $threshold);
    }

    private function makeInvoice(float $unitPrice): \App\Models\Invoice
    {
        $product = $this->createProduct();
        $result = $this->buy([$this->productItem($product, 1, $unitPrice)], approved: false, number: ++$this->nextInvoiceNumber);

        return $result['invoice'];
    }

    public function test_an_invoice_below_threshold_uses_the_unchanged_single_step_approval(): void
    {
        $this->setThreshold(10_000);

        $creator = $this->actingAsUserWith(['invoices.store']);
        $invoice = $this->makeInvoice(100);

        $this->actingAs($creator);
        $this->approveInvoice($invoice);

        $this->assertTrue($invoice->refresh()->status->isApproved());
        $this->assertNotNull($invoice->document_id);
    }

    public function test_no_threshold_configured_uses_the_unchanged_single_step_approval(): void
    {
        $creator = $this->actingAsUserWith(['invoices.store']);
        $invoice = $this->makeInvoice(999_999);

        $this->actingAs($creator);
        $this->approveInvoice($invoice);

        $this->assertTrue($invoice->refresh()->status->isApproved());
    }

    public function test_an_invoice_at_or_above_threshold_requires_a_first_approval_step_that_does_not_post(): void
    {
        $this->setThreshold(100);

        $creator = $this->actingAsUserWith(['invoices.store']);
        $invoice = $this->makeInvoice(100);

        $this->actingAs($creator);
        $this->approveInvoice($invoice);

        $invoice->refresh();
        $this->assertTrue($invoice->status->isPendingFinalApproval());
        $this->assertNull($invoice->document_id);
    }

    public function test_final_approval_by_a_different_user_completes_posting(): void
    {
        $this->setThreshold(100);

        $creator = $this->actingAsUserWith(['invoices.store']);
        $invoice = $this->makeInvoice(100);
        $this->actingAs($creator);
        $this->approveInvoice($invoice);

        $finalApprover = User::factory()->create();
        $this->actingAs($finalApprover);
        $this->approveInvoice($invoice->fresh());

        $invoice->refresh();
        $this->assertTrue($invoice->status->isApproved());
        $this->assertNotNull($invoice->document_id);
        $this->assertSame($creator->id, $invoice->first_approved_by);
    }

    public function test_final_approval_by_the_same_user_as_the_first_approval_is_rejected(): void
    {
        $this->setThreshold(100);

        $creator = $this->actingAsUserWith(['invoices.store']);
        $invoice = $this->makeInvoice(100);

        $firstApprover = User::factory()->create();
        $this->actingAs($firstApprover);
        $this->approveInvoice($invoice);

        $this->expectExceptionMessageMatches('/different user than the first approval/');
        $this->approveInvoice($invoice->fresh());
    }

    public function test_final_approve_route_requires_the_final_approve_permission_not_the_ordinary_approve_permission(): void
    {
        $this->setThreshold(100);

        $creator = $this->actingAsUserWith(['invoices.store', 'invoices.change-status', 'invoices.approve']);
        $invoice = $this->makeInvoice(100);
        $this->actingAs($creator);
        $this->approveInvoice($invoice);

        $finalApprover = $this->actingAsUserWith(['invoices.change-status', 'invoices.approve']);
        $response = $this->post(route('invoices.change-status', [$invoice->fresh(), 'approved']));

        $response->assertForbidden();
        $this->assertTrue($invoice->refresh()->status->isPendingFinalApproval());
    }

    public function test_final_approve_route_succeeds_with_the_final_approve_permission(): void
    {
        $this->setThreshold(100);

        $creator = $this->actingAsUserWith(['invoices.store', 'invoices.change-status', 'invoices.approve']);
        $invoice = $this->makeInvoice(100);
        $this->actingAs($creator);
        $this->approveInvoice($invoice);

        $this->actingAsUserWith(['invoices.change-status', 'invoices.final-approve']);
        $response = $this->post(route('invoices.change-status', [$invoice->fresh(), 'approved']));

        $response->assertRedirect();
        $this->assertTrue($invoice->refresh()->status->isApproved());
    }

    public function test_the_creator_cannot_be_the_first_approver_when_self_approval_prevention_is_enabled(): void
    {
        app(WorkflowGovernanceSettingsService::class)->update($this->companyId, true, 100);

        $creator = $this->actingAsUserWith(['invoices.store']);
        $invoice = $this->makeInvoice(100);

        $this->actingAs($creator);
        $this->expectExceptionMessageMatches('/created it/');
        $this->approveInvoice($invoice);
    }

    public function test_the_creator_cannot_be_the_final_approver_when_self_approval_prevention_is_enabled(): void
    {
        app(WorkflowGovernanceSettingsService::class)->update($this->companyId, true, 100);

        $creator = $this->actingAsUserWith(['invoices.store']);
        $invoice = $this->makeInvoice(100);

        $firstApprover = User::factory()->create();
        $this->actingAs($firstApprover);
        $this->approveInvoice($invoice);

        $this->actingAs($creator);
        $this->expectExceptionMessageMatches('/created it/');
        $this->approveInvoice($invoice->fresh());
    }
}
