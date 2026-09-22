<?php

namespace Tests\Feature;

use App\Enums\PayrollStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PersonnelRequest;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\AncillaryCostService;
use App\Services\WorkflowGovernanceSettingsService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * P7.2 (Segregation of Duties). Opt-in per company, default OFF (owner's
 * explicit decision -- most of this app's target SMB customers are a
 * single bookkeeper who both creates and approves their own documents;
 * see SegregationOfDutiesService's own docblock). Every self-approval
 * event is logged to the activity log regardless of the setting.
 */
class SegregationOfDutiesTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 8900;

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

    private function enableSelfApprovalPrevention(): void
    {
        app(WorkflowGovernanceSettingsService::class)->update($this->companyId, true, null);
    }

    // -----------------------------------------------------------------
    // Invoice
    // -----------------------------------------------------------------

    public function test_invoice_self_approval_is_allowed_by_default(): void
    {
        $creator = $this->actingAsUserWith(['invoices.store']);
        $product = $this->createProduct();
        $result = $this->buy([$this->productItem($product, 1, 100)], approved: false, number: ++$this->nextInvoiceNumber);

        $this->actingAs($creator);
        $this->approveInvoice($result['invoice']);

        $this->assertTrue($result['invoice']->refresh()->status->isApproved());
    }

    public function test_invoice_self_approval_is_blocked_when_setting_enabled(): void
    {
        $this->enableSelfApprovalPrevention();

        $creator = $this->actingAsUserWith(['invoices.store']);
        $product = $this->createProduct();
        $result = $this->buy([$this->productItem($product, 1, 100)], approved: false, number: ++$this->nextInvoiceNumber);

        $this->actingAs($creator);
        $this->expectExceptionMessageMatches('/created it/');
        $this->approveInvoice($result['invoice']);
    }

    public function test_invoice_approval_by_a_different_user_is_allowed_when_setting_enabled(): void
    {
        $this->enableSelfApprovalPrevention();

        $creator = $this->actingAsUserWith(['invoices.store']);
        $product = $this->createProduct();
        $result = $this->buy([$this->productItem($product, 1, 100)], approved: false, number: ++$this->nextInvoiceNumber);

        $approver = User::factory()->create();
        $this->actingAs($approver);
        $this->approveInvoice($result['invoice']);

        $this->assertTrue($result['invoice']->refresh()->status->isApproved());
    }

    // -----------------------------------------------------------------
    // AncillaryCost
    // -----------------------------------------------------------------

    private function buyInvoiceForAncillaryCost(): \App\Models\Invoice
    {
        $product = $this->createProduct();

        return $this->findInvoice(
            $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber)['invoice']->id
        );
    }

    public function test_ancillary_cost_self_approval_is_blocked_when_setting_enabled(): void
    {
        $this->enableSelfApprovalPrevention();

        $creator = $this->actingAsUserWith(['invoices.ancillary-costs.store', 'ancillary-costs.approve', 'ancillary-costs.change-status']);
        $invoice = $this->buyInvoiceForAncillaryCost();
        $productId = (int) $invoice->items->first()->itemable_id;

        $result = AncillaryCostService::createAncillaryCost($creator, [
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

        $response = $this->post(route('ancillary-costs.change-status', [$ancillaryCost, 'approve']));

        $response->assertSessionHasErrors('approval');
        $this->assertFalse($ancillaryCost->refresh()->status->isApproved());
    }

    public function test_ancillary_cost_approval_by_a_different_user_is_allowed_when_setting_enabled(): void
    {
        $this->enableSelfApprovalPrevention();

        $creator = $this->actingAsUserWith(['invoices.ancillary-costs.store']);
        $invoice = $this->buyInvoiceForAncillaryCost();
        $productId = (int) $invoice->items->first()->itemable_id;

        $result = AncillaryCostService::createAncillaryCost($creator, [
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

    // -----------------------------------------------------------------
    // PersonnelRequest
    // -----------------------------------------------------------------

    public function test_personnel_request_self_approval_is_blocked_when_setting_enabled(): void
    {
        $this->enableSelfApprovalPrevention();

        $employee = Employee::factory()->create(['company_id' => $this->companyId]);
        $submitter = $this->actingAsUserWith(['hr.personnel-requests.approve']);

        $personnelRequest = PersonnelRequest::withoutGlobalScopes()->create([
            'employee_id' => $employee->id,
            'company_id' => $this->companyId,
            'created_by' => $submitter->id,
            'request_type' => \App\Enums\PersonnelRequestType::LEAVE_DAILY,
            'start_date' => now(),
            'end_date' => now()->addHour(),
            'status' => \App\Enums\PersonnelRequestStatus::PENDING,
        ]);

        $response = $this->patch(route('hr.personnel-requests.approve', $personnelRequest));

        $response->assertSessionHasErrors('approval');
        $this->assertFalse($personnelRequest->refresh()->status->isApproved());
    }

    public function test_personnel_request_approval_by_a_different_user_is_allowed_when_setting_enabled(): void
    {
        $this->enableSelfApprovalPrevention();

        $employee = Employee::factory()->create(['company_id' => $this->companyId]);
        $submitter = User::factory()->create();
        $this->companyId && $submitter->givePermissionTo(Permission::firstOrCreate(['name' => 'hr.personnel-requests.approve']));

        $personnelRequest = PersonnelRequest::withoutGlobalScopes()->create([
            'employee_id' => $employee->id,
            'company_id' => $this->companyId,
            'created_by' => $submitter->id,
            'request_type' => \App\Enums\PersonnelRequestType::LEAVE_DAILY,
            'start_date' => now(),
            'end_date' => now()->addHour(),
            'status' => \App\Enums\PersonnelRequestStatus::PENDING,
        ]);

        $this->actingAsUserWith(['hr.personnel-requests.approve']);
        $response = $this->patch(route('hr.personnel-requests.approve', $personnelRequest));

        $response->assertRedirect();
        $this->assertTrue($personnelRequest->refresh()->status->isApproved());
    }

    // -----------------------------------------------------------------
    // Payroll
    // -----------------------------------------------------------------

    private function makePayroll(int $createdBy): Payroll
    {
        $employee = Employee::factory()->create(['company_id' => $this->companyId]);

        return Payroll::withoutGlobalScopes()->create([
            'company_id' => $this->companyId,
            'employee_id' => $employee->id,
            'created_by' => $createdBy,
            'year' => 1405,
            'month' => 1,
            'total_earnings' => 10_000_000,
            'total_deductions' => 1_000_000,
            'net_payment' => 9_000_000,
            'employer_insurance' => 2_000_000,
            'tax_base_amount' => 9_000_000,
            'income_tax_amount' => 500_000,
            'status' => PayrollStatus::PendingManagerApproval,
        ]);
    }

    public function test_payroll_self_approval_is_blocked_when_setting_enabled(): void
    {
        $this->enableSelfApprovalPrevention();

        $creator = $this->actingAsUserWith(['salary.payrolls.transition.pending-manager-approval-to-approved']);
        $payroll = $this->makePayroll($creator->id);

        $response = $this->patch(route('salary.payrolls.transition.pending-manager-approval-to-approved', $payroll));

        $response->assertSessionHasErrors('approval');
        $this->assertSame(PayrollStatus::PendingManagerApproval, $payroll->refresh()->status);
    }

    public function test_payroll_approval_by_a_different_user_is_allowed_when_setting_enabled(): void
    {
        $this->enableSelfApprovalPrevention();

        $creator = User::factory()->create();
        $payroll = $this->makePayroll($creator->id);

        $this->actingAsUserWith(['salary.payrolls.transition.pending-manager-approval-to-approved']);
        $response = $this->patch(route('salary.payrolls.transition.pending-manager-approval-to-approved', $payroll));

        $response->assertRedirect();
        $this->assertSame(PayrollStatus::Approved, $payroll->refresh()->status);
    }

    // -----------------------------------------------------------------
    // Cross-cutting
    // -----------------------------------------------------------------

    public function test_self_approval_is_logged_to_the_activity_log_even_when_the_setting_is_disabled(): void
    {
        $creator = $this->actingAsUserWith(['invoices.store']);
        $product = $this->createProduct();
        $result = $this->buy([$this->productItem($product, 1, 100)], approved: false, number: ++$this->nextInvoiceNumber);

        $this->actingAs($creator);
        $this->approveInvoice($result['invoice']);

        // This app's activity_log migration renames Spatie's default
        // columns: `source` (not `log_name`), `user_id` (not `causer_id`).
        $this->assertDatabaseHas('activity_log', [
            'source' => 'security',
            'description' => 'self_approval',
            'user_id' => $creator->id,
        ]);
    }

    public function test_self_approval_prevention_setting_is_isolated_per_company(): void
    {
        $otherCompanyId = Company::factory()->create()->id;

        app(WorkflowGovernanceSettingsService::class)->update($this->companyId, true, null);

        config(['active-company-id' => $otherCompanyId]);
        app(WorkflowGovernanceSettingsService::class)->update($otherCompanyId, false, null);

        $this->assertDatabaseHas('configs', [
            'key' => 'self_approval_prevented',
            'company_id' => $this->companyId,
            'value' => '1',
        ]);
        $this->assertDatabaseHas('configs', [
            'key' => 'self_approval_prevented',
            'company_id' => $otherCompanyId,
            'value' => '0',
        ]);
    }
}
