<?php

namespace Tests\Feature;

use App\Enums\AncillaryCostType;
use App\Enums\InvoiceStatus;
use App\Models\AncillaryCost;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\SeasonalTaxReportService;
use App\Services\TaxEvidenceService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;
use ZipArchive;

/** T-1: one downloadable ZIP bundling a period's tax evidence into a single hand-off pack. */
class TaxAuditPackTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9800;

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
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'tax-center.audit-pack']));
    }

    public function test_downloading_the_pack_needs_its_own_permission(): void
    {
        $this->user->revokePermissionTo('tax-center.audit-pack');
        [$year] = gregorian_to_jalali(now()->year, now()->month, now()->day);

        $this->get(route('tax-center.audit-pack', ['jalali_year' => $year, 'quarter' => 1]))->assertForbidden();
    }

    public function test_the_zip_contains_the_expected_files_and_the_periods_data(): void
    {
        [$year] = gregorian_to_jalali(now()->year, now()->month, now()->day);
        $service = app(SeasonalTaxReportService::class);
        [$start] = $service->quarterDateRange($year, 1);

        $product = $this->createProduct();
        $bought = $this->buy([array_merge($this->productItem($product, 10, 100), ['vat' => 0])], true, ++$this->nextInvoiceNumber, $start);
        $this->sell([array_merge($this->productItem($product, 1, 1000), ['vat' => 10])], true, ++$this->nextInvoiceNumber, $start);

        $ancillaryCost = AncillaryCost::create([
            'number' => 1, 'type' => AncillaryCostType::cases()[0]->value, 'amount' => 100, 'vat' => 0,
            'date' => $start, 'invoice_id' => $bought['invoice']->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::APPROVED->value, 'company_id' => $this->companyId,
        ]);
        app(TaxEvidenceService::class)->store(
            $ancillaryCost,
            UploadedFile::fake()->create('receipt.pdf', 5),
            'invoice',
            'Receipt',
            $this->companyId,
            $this->user->id,
        );

        $response = $this->get(route('tax-center.audit-pack', ['jalali_year' => $year, 'quarter' => 1]));
        $response->assertOk();
        $response->assertHeader('content-disposition');

        $path = tempnam(sys_get_temp_dir(), 'audit_pack_test_').'.zip';
        file_put_contents($path, $response->streamedContent());

        $zip = new ZipArchive;
        $zip->open($path);

        $this->assertNotFalse($zip->locateName('manifest.txt'));
        $this->assertNotFalse($zip->locateName('invoices.csv'));
        $this->assertNotFalse($zip->locateName('seasonal-vat-report.csv'));
        $this->assertNotFalse($zip->locateName('moadian-status-summary.csv'));

        $invoicesCsv = $zip->getFromName('invoices.csv');
        $this->assertStringContainsString('1000', $invoicesCsv);

        $foundEvidence = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_starts_with($zip->getNameIndex($i), 'evidence/')) {
                $foundEvidence = true;
            }
        }
        $this->assertTrue($foundEvidence);

        $zip->close();
        unlink($path);
    }

    public function test_another_companys_invoices_and_evidence_never_appear_in_the_pack(): void
    {
        [$year] = gregorian_to_jalali(now()->year, now()->month, now()->day);
        $service = app(SeasonalTaxReportService::class);
        [$start] = $service->quarterDateRange($year, 1);

        $product = $this->createProduct();
        $this->buy([array_merge($this->productItem($product, 10, 100), ['vat' => 0])], true, ++$this->nextInvoiceNumber, $start);
        $this->sell([array_merge($this->productItem($product, 1, 4242), ['vat' => 10])], true, ++$this->nextInvoiceNumber, $start);

        $other = Company::factory()->create(['fiscal_year' => 1405]);
        config(['active-company-id' => $other->id]);
        $otherGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $other->id]);
        $otherCustomer = Customer::factory()->withGroup($otherGroup)->withSubject()->create(['company_id' => $other->id]);
        $otherInvoice = Invoice::create([
            'customer_id' => $otherCustomer->id, 'number' => 1, 'date' => $start,
            'invoice_type' => 1, 'status' => 1, 'amount' => 999999, 'vat' => 0, 'company_id' => $other->id,
        ]);
        $otherAncillary = AncillaryCost::create([
            'number' => 1, 'type' => AncillaryCostType::cases()[0]->value, 'amount' => 999999, 'vat' => 0,
            'date' => $start, 'invoice_id' => $otherInvoice->id, 'customer_id' => $otherCustomer->id, 'status' => InvoiceStatus::APPROVED->value, 'company_id' => $other->id,
        ]);
        app(TaxEvidenceService::class)->store(
            $otherAncillary, UploadedFile::fake()->create('secret.pdf', 5), 'invoice', 'Secret', $other->id, $this->user->id,
        );
        config(['active-company-id' => $this->companyId]);

        $response = $this->get(route('tax-center.audit-pack', ['jalali_year' => $year, 'quarter' => 1]));
        $path = tempnam(sys_get_temp_dir(), 'audit_pack_test_').'.zip';
        file_put_contents($path, $response->streamedContent());

        $zip = new ZipArchive;
        $zip->open($path);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $this->assertStringNotContainsString('secret.pdf', $zip->getNameIndex($i));
        }
        $zip->close();
        unlink($path);
    }
}
