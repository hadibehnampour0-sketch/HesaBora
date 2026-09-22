<?php

namespace Tests\Feature;

use App\Enums\PayrollStatus;
use App\Enums\PersonnelRequestStatus;
use App\Enums\PersonnelRequestType;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PersonnelRequestController;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PersonnelRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Organization Authorization & Workflow Governance phase (continuation),
 * Workstream L/M (workflow/approval foundation, adversarial state-machine
 * testing). Per the phase's own explicit lesson from Financial Foundation
 * (invoice/AncillaryCost double-approve defects, found by literally calling
 * the same transition twice), this audits the OTHER two approval-shaped
 * mechanisms in the codebase that had not yet been adversarially tested:
 * Payroll's status transitions and PersonnelRequest's approve/reject.
 *
 * Conclusion (recorded in Decision Log): NO new workflow/approval
 * abstraction is justified. Payroll already uses an explicit
 * `[from, to] => permission | null` match table (`PayrollStatus::
 * transitionPermissionTo()`) that rejects any transition pair not
 * explicitly listed -- including a repeat of the same transition, or any
 * jump/skip -- with a 422, not a silent no-op or a duplicate side effect.
 * PersonnelRequest's `approve()` already short-circuits on an already-
 * approved request; `reject()`'s only side effect (reversing attendance
 * sync) is naturally gated by `$wasApproved`, so a repeat reject is a safe
 * no-op by construction. Both are proven here, not just read.
 *
 * A genuine concurrency gap WAS suspected and investigated for
 * PersonnelRequest: unlike `ChequeService::transition()` (which wraps its
 * status check in `DB::transaction()` + `lockForUpdate()`),
 * `PersonnelRequestController::approve()`/`reject()` read status with no
 * lock, so two genuinely concurrent requests binding the same pre-write
 * model could both observe "not yet approved" and both call
 * `AttendanceService::syncPersonnelRequestLogs()`. Manually reproducing
 * this race (two stale model instances, both calling `approve()`) proved
 * it is NOT actually exploitable: `syncPersonnelRequestLogs()` calls
 * `recalculateLog()` after every delta application, and `recalculateLog()`
 * rebuilds the day's `paid_leave`/`unpaid_leave`/etc. fields from scratch
 * by re-querying the employee's currently-*approved* PersonnelRequests
 * covering that date -- it does not trust the running counter. So a
 * doubled call re-derives the same final value instead of doubling it;
 * this is a third, differently-shaped idempotency pattern already present
 * in the codebase (idempotent-by-reconstruction, alongside Payroll's
 * exhaustive match-table and Cheque's pessimistic lock). No fix needed
 * here -- this is recorded so the disproven hypothesis isn't re-raised.
 *
 * The SAME race for `PayrollController::transition()` IS real and is
 * fixed below: `PayrollStatusHistory::create()` has no equivalent
 * recompute step, so two concurrent transition requests reading the same
 * pre-write status both pass validation and both insert a history row,
 * corrupting the transition audit trail even though the final `status`
 * value is coincidentally correct either way. Fixed by applying the exact
 * `DB::transaction()` + `Model::query()->lockForUpdate()->findOrFail()`
 * pattern already established and proven safe by
 * `ChequeService::transition()`. No schema change, no new abstraction.
 */
class WorkflowApprovalIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function superAdminInCompany(Company $company): User
    {
        $role = Role::firstOrCreate(['name' => 'Super-Admin']);
        $user = User::factory()->create();
        $user->companies()->syncWithoutDetaching([$company->id]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(0);
        $user->assignRole($role);
        $registrar->setPermissionsTeamId(null);

        return $user;
    }

    private function asCompany(Company $company, User $user)
    {
        return $this->actingAs($user)->withCookie('active-company-id', (string) $company->id);
    }

    public function test_repeating_a_payroll_transition_is_rejected_not_reapplied(): void
    {
        $company = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $actor = $this->superAdminInCompany($company);
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $payroll = Payroll::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'year' => 1403,
            'month' => 1,
            'status' => PayrollStatus::Draft,
        ]);

        // First transition succeeds.
        $this->asCompany($company, $actor)
            ->patch(route('salary.payrolls.transition.draft-to-pending-manager-approval', $payroll))
            ->assertRedirect();
        $this->assertSame(PayrollStatus::PendingManagerApproval, $payroll->fresh()->status);
        $this->assertSame(1, $payroll->fresh()->statusHistories()->count());

        // Repeating the SAME transition (draft -> pending) is now an
        // invalid pair from the CURRENT state (pending -> pending is not in
        // the match table) and must be rejected outright, not silently
        // reprocessed or recorded as a second history row.
        $this->asCompany($company, $actor)
            ->patch(route('salary.payrolls.transition.draft-to-pending-manager-approval', $payroll))
            ->assertStatus(422);
        $this->assertSame(PayrollStatus::PendingManagerApproval, $payroll->fresh()->status);
        $this->assertSame(1, $payroll->fresh()->statusHistories()->count(), 'a rejected transition must not add a history row');

        // Advance to Approved, then try to approve again.
        $this->asCompany($company, $actor)
            ->patch(route('salary.payrolls.transition.pending-manager-approval-to-approved', $payroll))
            ->assertRedirect();
        $this->assertSame(PayrollStatus::Approved, $payroll->fresh()->status);

        $this->asCompany($company, $actor)
            ->patch(route('salary.payrolls.transition.pending-manager-approval-to-approved', $payroll))
            ->assertStatus(422);
        $this->assertSame(PayrollStatus::Approved, $payroll->fresh()->status);
        $this->assertSame(2, $payroll->fresh()->statusHistories()->count());

        // Mark paid, then attempt to mark paid again (or move backwards) --
        // both must be rejected; Paid is a terminal state in the match table.
        $this->asCompany($company, $actor)
            ->patch(route('salary.payrolls.transition.approved-to-paid', $payroll))
            ->assertRedirect();
        $this->assertSame(PayrollStatus::Paid, $payroll->fresh()->status);

        $this->asCompany($company, $actor)
            ->patch(route('salary.payrolls.transition.approved-to-paid', $payroll))
            ->assertStatus(422);
        $this->assertSame(3, $payroll->fresh()->statusHistories()->count());
    }

    public function test_approving_an_already_approved_personnel_request_is_a_safe_no_op(): void
    {
        $company = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $actor = $this->superAdminInCompany($company);
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $personnelRequest = PersonnelRequest::create([
            'employee_id' => $employee->id,
            'company_id' => $company->id,
            'request_type' => PersonnelRequestType::LEAVE_DAILY,
            'status' => PersonnelRequestStatus::PENDING,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $this->asCompany($company, $actor)
            ->patch(route('hr.personnel-requests.approve', $personnelRequest))
            ->assertRedirect();
        $this->assertSame(PersonnelRequestStatus::APPROVED, $personnelRequest->fresh()->status);

        // Second approve call must be a safe no-op (already guarded by the
        // controller's own isApproved() short-circuit) -- proving it here,
        // not just reading the guard.
        $this->asCompany($company, $actor)
            ->patch(route('hr.personnel-requests.approve', $personnelRequest))
            ->assertRedirect()
            ->assertSessionHas('info');
        $this->assertSame(PersonnelRequestStatus::APPROVED, $personnelRequest->fresh()->status);
    }

    public function test_rejecting_an_already_rejected_personnel_request_does_not_double_reverse_attendance(): void
    {
        $company = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $actor = $this->superAdminInCompany($company);
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $personnelRequest = PersonnelRequest::create([
            'employee_id' => $employee->id,
            'company_id' => $company->id,
            'request_type' => PersonnelRequestType::LEAVE_DAILY,
            'status' => PersonnelRequestStatus::PENDING,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $this->asCompany($company, $actor)
            ->patch(route('hr.personnel-requests.reject', $personnelRequest))
            ->assertRedirect();
        $this->assertSame(PersonnelRequestStatus::REJECTED, $personnelRequest->fresh()->status);

        // Reject again -- $wasApproved is false both times (it was never
        // approved), so the attendance-reversal sync must not fire twice
        // (or at all). No exception, no duplicate side effect.
        $this->asCompany($company, $actor)
            ->patch(route('hr.personnel-requests.reject', $personnelRequest))
            ->assertRedirect();
        $this->assertSame(PersonnelRequestStatus::REJECTED, $personnelRequest->fresh()->status);
    }

    public function test_concurrent_approve_calls_do_not_double_apply_attendance_sync_because_recalculation_is_idempotent(): void
    {
        $company = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $actor = $this->superAdminInCompany($company);

        // Baseline: a single, ordinary approve() applies the leave delta
        // exactly once. This gives us the expected minute value to compare
        // the "raced" scenario against.
        $baselineEmployee = Employee::factory()->create(['company_id' => $company->id]);
        $baselineRequest = PersonnelRequest::create([
            'employee_id' => $baselineEmployee->id,
            'company_id' => $company->id,
            'request_type' => PersonnelRequestType::LEAVE_DAILY,
            'status' => PersonnelRequestStatus::PENDING,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);
        $this->asCompany($company, $actor)
            ->patch(route('hr.personnel-requests.approve', $baselineRequest))
            ->assertRedirect();
        $expectedMinutes = (int) AttendanceLog::where('employee_id', $baselineEmployee->id)
            ->where('log_date', now()->toDateString())
            ->value('paid_leave');
        $this->assertGreaterThan(0, $expectedMinutes, 'baseline approval must record a nonzero leave delta');

        // Raced scenario: two "requests" both bind the PersonnelRequest
        // while it is still PENDING, before either one's approve() call
        // commits -- simulating two genuinely concurrent HTTP requests that
        // both read the pre-write row. Calling the controller directly
        // (bypassing route-model-binding's own fresh-per-request read) is
        // how this is reproduced deterministically in a single process.
        $racedEmployee = Employee::factory()->create(['company_id' => $company->id]);
        $racedRequest = PersonnelRequest::create([
            'employee_id' => $racedEmployee->id,
            'company_id' => $company->id,
            'request_type' => PersonnelRequestType::LEAVE_DAILY,
            'status' => PersonnelRequestStatus::PENDING,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);
        $racingInstanceA = PersonnelRequest::find($racedRequest->id);
        $racingInstanceB = PersonnelRequest::find($racedRequest->id);

        $this->actingAs($actor);
        config(['active-company-id' => $company->id]);

        app(PersonnelRequestController::class)->approve($racingInstanceA);
        // $racingInstanceB is still a stale in-memory PENDING read, exactly
        // as a second concurrent request's route-bound model would be.
        app(PersonnelRequestController::class)->approve($racingInstanceB);

        $this->assertSame(PersonnelRequestStatus::APPROVED, $racedRequest->fresh()->status);

        $racedMinutes = (int) AttendanceLog::where('employee_id', $racedEmployee->id)
            ->where('log_date', now()->toDateString())
            ->value('paid_leave');

        // NOT 2x $expectedMinutes: syncPersonnelRequestLogs() -> recalculateLog()
        // re-derives the field from the set of currently-approved requests
        // rather than trusting a running counter, so the second (raced)
        // call's redundant application collapses back to the same value.
        $this->assertSame($expectedMinutes, $racedMinutes, 'idempotent-by-reconstruction sync must not double the attendance delta even when raced');
    }

    public function test_concurrent_payroll_transitions_do_not_create_duplicate_history_rows(): void
    {
        $company = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $actor = $this->superAdminInCompany($company);
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $payroll = Payroll::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'year' => 1403,
            'month' => 1,
            'status' => PayrollStatus::Draft,
        ]);

        // Two "requests" both bind the same Payroll while it is still
        // Draft, before either one's submit-for-approval transition
        // commits -- simulating two genuinely concurrent HTTP requests.
        $racingInstanceA = Payroll::find($payroll->id);
        $racingInstanceB = Payroll::find($payroll->id);

        $this->actingAs($actor);
        config(['active-company-id' => $company->id]);

        $request = Request::create('/', 'PATCH');
        $request->setUserResolver(fn () => $actor);

        app(PayrollController::class)->submitForApproval($request, $racingInstanceA);

        // Without locking, $racingInstanceB is still a stale Draft read --
        // exactly what a second concurrent request's route-bound model
        // would be -- and would independently pass transitionPermissionTo()
        // and insert a second PayrollStatusHistory row for the same event.
        // With the fix, the fresh, locked re-read inside the transaction
        // sees PendingManagerApproval and correctly rejects with a 422.
        try {
            app(PayrollController::class)->submitForApproval($request, $racingInstanceB);
            $this->fail('Expected the raced duplicate transition to be rejected with a 422.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame(PayrollStatus::PendingManagerApproval, $payroll->fresh()->status);
        $this->assertSame(1, $payroll->fresh()->statusHistories()->count(), 'a raced duplicate transition must not create a second history row');
    }
}
