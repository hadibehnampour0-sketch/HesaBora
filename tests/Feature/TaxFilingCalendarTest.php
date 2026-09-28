<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\ProductGroup;
use App\Models\TaxAlert;
use App\Models\TaxFilingRecord;
use App\Models\User;
use App\Services\TaxAlertEngineService;
use App\Services\TaxFilingCalendarService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/** T-3: the per-quarter filing calendar and its "overdue" alert. */
class TaxFilingCalendarTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

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
        $this->actingAs($this->user);
    }

    private function grant(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
    }

    public function test_the_calendar_needs_its_own_permission(): void
    {
        $this->get(route('tax-center.calendar'))->assertForbidden();
    }

    public function test_a_past_quarter_with_nothing_filed_is_overdue(): void
    {
        $this->grant('tax-center.calendar');

        [$year] = gregorian_to_jalali(now()->year, now()->month, now()->day);
        $rows = app(TaxFilingCalendarService::class)->calendar($this->companyId, $year - 2);

        $this->assertSame(TaxFilingCalendarService::STATUS_OVERDUE, $rows[0]['status']);
    }

    public function test_marking_a_quarter_filed_updates_its_status_and_is_idempotent(): void
    {
        $this->grant('tax-center.calendar', 'tax-center.calendar.mark-filed');

        [$year] = gregorian_to_jalali(now()->year, now()->month, now()->day);
        $targetYear = $year - 2;

        $this->post(route('tax-center.calendar.mark-filed'), ['jalali_year' => $targetYear, 'quarter' => 1])->assertSessionHasNoErrors();
        // Marking again must not create a second row (unique per company/year/quarter).
        $this->post(route('tax-center.calendar.mark-filed'), ['jalali_year' => $targetYear, 'quarter' => 1])->assertSessionHasNoErrors();

        $this->assertSame(1, TaxFilingRecord::where('jalali_year', $targetYear)->where('quarter', 1)->count());

        $rows = app(TaxFilingCalendarService::class)->calendar($this->companyId, $targetYear);
        $this->assertSame(TaxFilingCalendarService::STATUS_FILED, $rows[0]['status']);

        $this->get(route('tax-center.calendar', ['jalali_year' => $targetYear]))->assertOk()->assertSee(__('Filed'));
    }

    public function test_another_companys_filing_records_never_leak_into_the_calendar(): void
    {
        $this->grant('tax-center.calendar', 'tax-center.calendar.mark-filed');
        [$year] = gregorian_to_jalali(now()->year, now()->month, now()->day);
        $targetYear = $year - 2;

        $this->post(route('tax-center.calendar.mark-filed'), ['jalali_year' => $targetYear, 'quarter' => 2]);

        $other = Company::factory()->create(['fiscal_year' => 1405]);
        config(['active-company-id' => $other->id]);
        $rows = app(TaxFilingCalendarService::class)->calendar($other->id, $targetYear);
        config(['active-company-id' => $this->companyId]);

        $this->assertSame(TaxFilingCalendarService::STATUS_OVERDUE, $rows[1]['status']);
    }

    public function test_the_overdue_filing_alert_is_raised_and_then_deduplicated(): void
    {
        // Jump forward far enough that the previous quarter's due date (its end + 15 days) has
        // definitely passed, with nothing ever marked filed -- deterministic regardless of
        // "today"'s actual position within the current quarter.
        Carbon::setTestNow(now()->addDays(100));

        try {
            app(TaxAlertEngineService::class)->evaluateCompanyRules($this->companyId);
            $this->assertGreaterThanOrEqual(1, TaxAlert::where('type', 'overdue_filing')->count());

            $totalAfterFirst = TaxAlert::where('type', 'overdue_filing')->count();
            app(TaxAlertEngineService::class)->evaluateCompanyRules($this->companyId);
            $this->assertSame($totalAfterFirst, TaxAlert::where('type', 'overdue_filing')->count());
        } finally {
            Carbon::setTestNow();
        }
    }
}
