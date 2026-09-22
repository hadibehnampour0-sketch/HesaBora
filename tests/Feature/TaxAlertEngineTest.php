<?php

namespace Tests\Feature;

use App\Enums\AncillaryCostType;
use App\Enums\InvoiceStatus;
use App\Jobs\EvaluateTaxAlertsJob;
use App\Models\AncillaryCost;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\MoadianHistory;
use App\Models\ProductGroup;
use App\Models\TaxAlert;
use App\Models\TaxEvidence;
use App\Models\TaxReadinessLog;
use App\Models\User;
use App\Services\TaxAlertEngineService;
use App\Services\TaxReadinessService;
use App\Services\TaxRuleService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * P12.2 (Tax Alert & Smart Notification Engine).
 */
class TaxAlertEngineTest extends TestCase
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

        $this->user = User::factory()->create();
        Company::find($this->companyId)->users()->syncWithoutDetaching($this->user);
        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'tax-alerts.index']),
            Permission::firstOrCreate(['name' => 'tax-alerts.read']),
            Permission::firstOrCreate(['name' => 'tax-alerts.read-all']),
        );
        $this->actingAs($this->user);

        app(TaxRuleService::class)->recordRule(null, 10.0, false, '2020-01-01', null, 'General');
    }

    public function test_a_low_readiness_score_raises_a_critical_alert(): void
    {
        TaxReadinessLog::create([
            'company_id' => $this->companyId,
            'score' => 30,
            'status' => 'critical',
            'metrics_breakdown' => [],
            'recommendations' => [],
            'calculated_at' => now(),
        ]);

        $raised = app(TaxAlertEngineService::class)->evaluateCompanyRules($this->companyId);

        $this->assertSame(1, $raised);
        $alert = TaxAlert::first();
        $this->assertSame('low_readiness_score', $alert->type);
        $this->assertSame(TaxAlert::SEVERITY_CRITICAL, $alert->severity);
    }

    public function test_a_healthy_readiness_score_does_not_raise_an_alert(): void
    {
        TaxReadinessLog::create([
            'company_id' => $this->companyId,
            'score' => 80,
            'status' => 'ready',
            'metrics_breakdown' => [],
            'recommendations' => [],
            'calculated_at' => now(),
        ]);

        $raised = app(TaxAlertEngineService::class)->evaluateCompanyRules($this->companyId);

        $this->assertSame(0, $raised);
        $this->assertSame(0, TaxAlert::count());
    }

    public function test_a_moadian_invoice_rejected_over_24_hours_ago_raises_an_alert(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 5, 50)], true, ++$this->nextInvoiceNumber);
        $invoice = $this->sell([$this->productItem($product, 1, 100)], true, ++$this->nextInvoiceNumber)['invoice'];

        $history = MoadianHistory::create(['invoice_id' => $invoice->id, 'data' => ['status' => 'FAILED', 'referenceNumber' => 'REF-1']]);
        $history->forceFill(['created_at' => Carbon::now()->subHours(30)])->save();

        $raised = app(TaxAlertEngineService::class)->evaluateCompanyRules($this->companyId);

        $this->assertSame(1, $raised);
        $this->assertSame('rejected_moadian_invoice', TaxAlert::first()->type);
    }

    public function test_a_recently_rejected_moadian_invoice_does_not_yet_raise_an_alert(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 5, 50)], true, ++$this->nextInvoiceNumber);
        $invoice = $this->sell([$this->productItem($product, 1, 100)], true, ++$this->nextInvoiceNumber)['invoice'];

        MoadianHistory::create(['invoice_id' => $invoice->id, 'data' => ['status' => 'FAILED', 'referenceNumber' => 'REF-1']]);

        $raised = app(TaxAlertEngineService::class)->evaluateCompanyRules($this->companyId);

        $this->assertSame(0, $raised);
    }

    public function test_a_high_value_ancillary_cost_missing_evidence_raises_an_alert(): void
    {
        $product = $this->createProduct();
        $invoice = $this->buy([$this->productItem($product, 5, 50)], true, ++$this->nextInvoiceNumber);

        $cost = AncillaryCost::create([
            'number' => 1,
            'type' => AncillaryCostType::Shipping,
            'amount' => TaxReadinessService::MATERIAL_EXPENSE_THRESHOLD + 1,
            'vat' => 0,
            'date' => now(),
            'invoice_id' => $invoice['invoice']->id,
            'status' => InvoiceStatus::APPROVED,
            'company_id' => $this->companyId,
            'customer_id' => $this->customer->id,
        ]);

        $raised = app(TaxAlertEngineService::class)->evaluateCompanyRules($this->companyId);

        $this->assertSame(1, $raised);
        $alert = TaxAlert::first();
        $this->assertSame('missing_evidence', $alert->type);
        $this->assertSame(AncillaryCost::class, $alert->related_type);
        $this->assertSame($cost->id, $alert->related_id);
    }

    public function test_a_high_value_ancillary_cost_with_evidence_does_not_raise_an_alert(): void
    {
        $product = $this->createProduct();
        $invoice = $this->buy([$this->productItem($product, 5, 50)], true, ++$this->nextInvoiceNumber);

        $cost = AncillaryCost::create([
            'number' => 1,
            'type' => AncillaryCostType::Shipping,
            'amount' => TaxReadinessService::MATERIAL_EXPENSE_THRESHOLD + 1,
            'vat' => 0,
            'date' => now(),
            'invoice_id' => $invoice['invoice']->id,
            'status' => InvoiceStatus::APPROVED,
            'company_id' => $this->companyId,
            'customer_id' => $this->customer->id,
        ]);

        TaxEvidence::create([
            'company_id' => $this->companyId,
            'evidenceable_type' => AncillaryCost::class,
            'evidenceable_id' => $cost->id,
            'document_type' => 'invoice_copy',
            'title' => 'Test Evidence',
            'file_name' => 'test.pdf',
            'file_path' => 'evidence/test.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'file_hash' => hash('sha256', 'test'),
            'uploaded_by' => $this->user->id,
        ]);

        $raised = app(TaxAlertEngineService::class)->evaluateCompanyRules($this->companyId);

        $this->assertSame(0, $raised);
    }

    public function test_the_same_condition_is_not_re_raised_within_24_hours(): void
    {
        TaxReadinessLog::create([
            'company_id' => $this->companyId,
            'score' => 30,
            'status' => 'critical',
            'metrics_breakdown' => [],
            'recommendations' => [],
            'calculated_at' => now(),
        ]);

        $service = app(TaxAlertEngineService::class);
        $service->evaluateCompanyRules($this->companyId);
        $second = $service->evaluateCompanyRules($this->companyId);

        $this->assertSame(0, $second);
        $this->assertSame(1, TaxAlert::count());
    }

    public function test_marking_an_alert_as_read_persists(): void
    {
        TaxReadinessLog::create([
            'company_id' => $this->companyId,
            'score' => 30,
            'status' => 'critical',
            'metrics_breakdown' => [],
            'recommendations' => [],
            'calculated_at' => now(),
        ]);

        app(TaxAlertEngineService::class)->evaluateCompanyRules($this->companyId);
        $alert = TaxAlert::first();

        $this->postJson(route('tax-alerts.read', $alert))->assertOk();

        $this->assertTrue($alert->fresh()->is_read);
    }

    public function test_a_user_cannot_read_another_companys_alert(): void
    {
        TaxReadinessLog::create([
            'company_id' => $this->companyId,
            'score' => 30,
            'status' => 'critical',
            'metrics_breakdown' => [],
            'recommendations' => [],
            'calculated_at' => now(),
        ]);
        app(TaxAlertEngineService::class)->evaluateCompanyRules($this->companyId);
        $companyAAlert = TaxAlert::first();

        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create();
        $otherCompany->users()->syncWithoutDetaching($otherUser);

        // Permissions are team-scoped by the active company at grant time
        // (see CompanyPermissionsTeamResolver) -- must switch first, or
        // this grant would attach under $this->companyId's team instead.
        config(['active-company-id' => $otherCompany->id]);
        $otherUser->givePermissionTo(
            Permission::firstOrCreate(['name' => 'tax-alerts.index']),
            Permission::firstOrCreate(['name' => 'tax-alerts.read']),
        );
        $this->actingAs($otherUser);

        // Route-model binding resolves via TaxAlert's own FiscalYearScope,
        // scoped to whichever company is active -- the other company's
        // alert must not resolve at all (404), the same guarantee every
        // other company-scoped resource controller in this app relies on.
        $this->postJson(route('tax-alerts.read', $companyAAlert))->assertNotFound();

        $response = $this->getJson(route('tax-alerts.index'));
        $response->assertOk();
        $this->assertSame(0, $response->json('unread_count'));
        $this->assertCount(0, $response->json('alerts'));

        $this->assertFalse($companyAAlert->fresh()->is_read);
    }

    public function test_the_job_evaluates_every_company_independently(): void
    {
        TaxReadinessLog::create([
            'company_id' => $this->companyId,
            'score' => 20,
            'status' => 'critical',
            'metrics_breakdown' => [],
            'recommendations' => [],
            'calculated_at' => now(),
        ]);

        $otherCompany = Company::factory()->create();
        TaxReadinessLog::withoutGlobalScopes()->create([
            'company_id' => $otherCompany->id,
            'score' => 10,
            'status' => 'critical',
            'metrics_breakdown' => [],
            'recommendations' => [],
            'calculated_at' => now(),
        ]);

        (new EvaluateTaxAlertsJob)->handle(app(TaxAlertEngineService::class));

        $this->assertSame(1, TaxAlert::where('company_id', $this->companyId)->count());
        $this->assertSame(1, TaxAlert::withoutGlobalScopes()->where('company_id', $otherCompany->id)->count());
    }
}
