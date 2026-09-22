<?php

namespace Tests\Feature;

use App\Enums\TaxEvidenceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\AncillaryCostService;
use App\Services\TaxEvidenceService;
use App\Services\TaxReadinessService;
use App\Services\TaxRuleService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class TaxReadinessServiceTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 8200;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

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

        // A general VAT rule is otherwise a hard failure for every test.
        app(TaxRuleService::class)->recordRule(null, 10.0, false, '2020-01-01', null, 'General');
    }

    public function test_a_fully_configured_company_with_no_issues_scores_100(): void
    {
        Company::find($this->companyId)->update([
            'economical_code' => '123',
            'national_code' => '456',
            'moadian_username' => 'A1',
            'tax_id' => 'T1',
            'certificate_path' => 'moadian/cert.pem',
            'private_key_path' => 'moadian/key.pem',
        ]);
        Storage::disk('local')->put('moadian/cert.pem', 'x');
        Storage::disk('local')->put('moadian/key.pem', 'x');

        $log = app(TaxReadinessService::class)->calculateScore($this->companyId);

        $this->assertSame(100, $log->score);
        $this->assertSame('optimal', $log->status);
        $this->assertSame([], $log->recommendations);
    }

    public function test_missing_profile_fields_deduct_points_and_add_a_recommendation(): void
    {
        $log = app(TaxReadinessService::class)->calculateScore($this->companyId);

        $this->assertLessThan(100, $log->score);
        $this->assertNotEmpty($log->recommendations);
        $this->assertSame(0, $log->metrics_breakdown['profile_readiness']['score']);
    }

    public function test_an_unsent_approved_sell_invoice_deducts_moadian_compliance_points(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 5, 50)], true, ++$this->nextInvoiceNumber);
        $this->sell([$this->productItem($product, 1, 100)], true, ++$this->nextInvoiceNumber);

        $log = app(TaxReadinessService::class)->calculateScore($this->companyId);

        $this->assertLessThan(35, $log->metrics_breakdown['moadian_compliance']['score']);
    }

    public function test_a_high_value_ancillary_cost_without_evidence_deducts_points(): void
    {
        $product = $this->createProduct();
        $invoice = $this->findInvoice(
            $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber)['invoice']->id
        );
        $productId = (int) $invoice->items->first()->itemable_id;

        AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'company_id' => $this->companyId,
            'date' => $invoice->date->toDateString(),
            'type' => 'Shipping',
            'amount' => TaxReadinessService::MATERIAL_EXPENSE_THRESHOLD + 1,
            'vatPrice' => 0,
            'ancillaryCosts' => [['product_id' => $productId, 'amount' => TaxReadinessService::MATERIAL_EXPENSE_THRESHOLD + 1]],
        ], false);

        $log = app(TaxReadinessService::class)->calculateScore($this->companyId);

        $this->assertLessThan(20, $log->metrics_breakdown['evidence_vault_coverage']['score']);
    }

    public function test_attaching_evidence_to_a_high_value_ancillary_cost_restores_full_points(): void
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
            'amount' => TaxReadinessService::MATERIAL_EXPENSE_THRESHOLD + 1,
            'vatPrice' => 0,
            'ancillaryCosts' => [['product_id' => $productId, 'amount' => TaxReadinessService::MATERIAL_EXPENSE_THRESHOLD + 1]],
        ], false);

        app(TaxEvidenceService::class)->store(
            $result['ancillaryCost'],
            UploadedFile::fake()->create('receipt.pdf', 5),
            TaxEvidenceType::PAYMENT_PROOF->value,
            'Receipt',
            $this->companyId,
            $this->user->id
        );

        $log = app(TaxReadinessService::class)->calculateScore($this->companyId);

        $this->assertSame(20, $log->metrics_breakdown['evidence_vault_coverage']['score']);
    }

    public function test_no_vat_rule_configured_scores_zero_on_that_metric(): void
    {
        \App\Models\TaxRule::query()->delete();

        $log = app(TaxReadinessService::class)->calculateScore($this->companyId);

        $this->assertSame(0, $log->metrics_breakdown['tax_rule_alignment']['score']);
    }

    public function test_scores_below_50_are_classified_critical(): void
    {
        \App\Models\TaxRule::query()->delete();

        // Profile incomplete (0/25) + no VAT rule (0/20) alone is 55
        // ("warning"); also fail Moadian compliance to genuinely cross
        // into "critical" territory.
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 5, 50)], true, ++$this->nextInvoiceNumber);
        $this->sell([$this->productItem($product, 1, 100)], true, ++$this->nextInvoiceNumber);
        $this->sell([$this->productItem($product, 1, 100)], true, ++$this->nextInvoiceNumber);

        $log = app(TaxReadinessService::class)->calculateScore($this->companyId);

        $this->assertLessThan(50, $log->score);
        $this->assertSame('critical', $log->status);
    }

    public function test_readiness_logs_are_isolated_per_company(): void
    {
        $otherCompanyId = Company::factory()->create()->id;

        app(TaxReadinessService::class)->calculateScore($this->companyId);

        config(['active-company-id' => $otherCompanyId]);
        $this->assertSame(0, \App\Models\TaxReadinessLog::count());
        config(['active-company-id' => $this->companyId]);

        $this->assertSame(1, \App\Models\TaxReadinessLog::count());
    }
}
