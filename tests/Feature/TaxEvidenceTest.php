<?php

namespace Tests\Feature;

use App\Enums\TaxEvidenceType;
use App\Models\AncillaryCost;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\ProductGroup;
use App\Models\TaxEvidence;
use App\Models\User;
use App\Services\AncillaryCostService;
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

class TaxEvidenceTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 8300;

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
        foreach (['tax-evidences.store', 'tax-evidences.download', 'tax-evidences.destroy'] as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
        $this->actingAs($this->user);
    }

    private function ancillaryCost(): AncillaryCost
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

        return $result['ancillaryCost'];
    }

    public function test_uploading_evidence_records_a_sha256_hash_and_stores_the_file_off_the_public_disk(): void
    {
        $ancillaryCost = $this->ancillaryCost();
        $file = UploadedFile::fake()->create('receipt.pdf', 10);

        $response = $this->post(route('tax-evidences.store', $ancillaryCost), [
            'title' => 'Shipping receipt',
            'document_type' => TaxEvidenceType::PAYMENT_PROOF->value,
            'file' => $file,
        ]);

        $response->assertRedirect();

        $evidence = TaxEvidence::where('evidenceable_id', $ancillaryCost->id)->where('evidenceable_type', $ancillaryCost->getMorphClass())->firstOrFail();
        $this->assertSame(64, strlen($evidence->file_hash));
        $this->assertSame($this->companyId, $evidence->company_id);
        Storage::disk('local')->assertExists($evidence->file_path);
    }

    public function test_verify_integrity_detects_a_tampered_file(): void
    {
        $ancillaryCost = $this->ancillaryCost();
        $file = UploadedFile::fake()->create('receipt.pdf', 10);
        $this->post(route('tax-evidences.store', $ancillaryCost), [
            'title' => 'Shipping receipt',
            'document_type' => TaxEvidenceType::PAYMENT_PROOF->value,
            'file' => $file,
        ]);
        $evidence = TaxEvidence::firstOrFail();

        $service = app(TaxEvidenceService::class);
        $this->assertTrue($service->verifyIntegrity($evidence));

        Storage::disk('local')->put($evidence->file_path, 'tampered-content');
        $this->assertFalse($service->verifyIntegrity($evidence));
    }

    public function test_verify_integrity_returns_false_when_the_file_is_missing(): void
    {
        $ancillaryCost = $this->ancillaryCost();
        $file = UploadedFile::fake()->create('receipt.pdf', 10);
        $this->post(route('tax-evidences.store', $ancillaryCost), [
            'title' => 'Shipping receipt',
            'document_type' => TaxEvidenceType::PAYMENT_PROOF->value,
            'file' => $file,
        ]);
        $evidence = TaxEvidence::firstOrFail();

        Storage::disk('local')->delete($evidence->file_path);

        $this->assertFalse(app(TaxEvidenceService::class)->verifyIntegrity($evidence));
    }

    public function test_a_user_from_another_company_cannot_download_this_companys_evidence(): void
    {
        $ancillaryCost = $this->ancillaryCost();
        $file = UploadedFile::fake()->create('receipt.pdf', 10);
        $this->post(route('tax-evidences.store', $ancillaryCost), [
            'title' => 'Shipping receipt',
            'document_type' => TaxEvidenceType::PAYMENT_PROOF->value,
            'file' => $file,
        ]);
        $evidence = TaxEvidence::firstOrFail();

        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create();
        $otherCompany->users()->syncWithoutDetaching($otherUser);
        $otherUser->givePermissionTo(Permission::firstOrCreate(['name' => 'tax-evidences.download']));

        config(['active-company-id' => $otherCompany->id]);
        $this->actingAs($otherUser);

        $response = $this->get(route('tax-evidences.download', $evidence));

        $response->assertNotFound();
    }

    public function test_deleting_evidence_removes_the_file_and_the_row(): void
    {
        $ancillaryCost = $this->ancillaryCost();
        $file = UploadedFile::fake()->create('receipt.pdf', 10);
        $this->post(route('tax-evidences.store', $ancillaryCost), [
            'title' => 'Shipping receipt',
            'document_type' => TaxEvidenceType::PAYMENT_PROOF->value,
            'file' => $file,
        ]);
        $evidence = TaxEvidence::firstOrFail();
        $path = $evidence->file_path;

        $response = $this->delete(route('tax-evidences.destroy', $evidence));

        $response->assertRedirect();
        $this->assertDatabaseMissing('tax_evidences', ['id' => $evidence->id]);
        Storage::disk('local')->assertMissing($path);
    }

    // --- T-2: the vault extended to Invoice, and the show-page widget ------------------

    public function test_evidence_can_be_attached_to_an_invoice(): void
    {
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'invoices.tax-evidences.store']));
        $product = $this->createProduct();
        $invoice = $this->findInvoice(
            $this->buy([$this->productItem($product, 5, 100)], true, ++$this->nextInvoiceNumber)['invoice']->id
        );

        $response = $this->post(route('invoices.tax-evidences.store', $invoice), [
            'title' => 'Supplier contract',
            'document_type' => TaxEvidenceType::CONTRACT->value,
            'file' => UploadedFile::fake()->create('contract.pdf', 10),
        ]);

        $response->assertRedirect();
        $evidence = TaxEvidence::where('evidenceable_id', $invoice->id)->where('evidenceable_type', $invoice->getMorphClass())->firstOrFail();
        $this->assertSame($this->companyId, $evidence->company_id);
        Storage::disk('local')->assertExists($evidence->file_path);
    }

    public function test_attaching_invoice_evidence_needs_its_own_permission_separate_from_ancillary_cost(): void
    {
        // tax-evidences.store (ancillary cost) is granted in setUp, but the invoice route needs
        // its own permission name (permission == route name), and must not be satisfied by it.
        $product = $this->createProduct();
        $invoice = $this->findInvoice(
            $this->buy([$this->productItem($product, 5, 100)], true, ++$this->nextInvoiceNumber)['invoice']->id
        );

        $this->post(route('invoices.tax-evidences.store', $invoice), [
            'title' => 'X', 'document_type' => TaxEvidenceType::OTHER->value, 'file' => UploadedFile::fake()->create('x.pdf', 1),
        ])->assertForbidden();
    }

    public function test_the_invoice_page_shows_and_uploads_evidence(): void
    {
        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'invoices.show']),
            Permission::firstOrCreate(['name' => 'invoices.tax-evidences.store']),
        );
        $product = $this->createProduct();
        $invoice = $this->findInvoice(
            $this->buy([$this->productItem($product, 5, 100)], true, ++$this->nextInvoiceNumber)['invoice']->id
        );
        $this->post(route('invoices.tax-evidences.store', $invoice), [
            'title' => 'Customs form', 'document_type' => TaxEvidenceType::OTHER->value, 'file' => UploadedFile::fake()->create('form.pdf', 5),
        ]);

        $this->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee(__('Tax evidence'))
            ->assertSee('Customs form');
    }

    public function test_the_ancillary_cost_page_shows_its_own_evidence(): void
    {
        $ancillaryCost = $this->ancillaryCost();
        $this->post(route('tax-evidences.store', $ancillaryCost), [
            'title' => 'Freight invoice', 'document_type' => TaxEvidenceType::INVOICE_COPY->value, 'file' => UploadedFile::fake()->create('freight.pdf', 5),
        ]);
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'invoices.ancillary-costs.show']));

        $this->get(route('invoices.ancillary-costs.show', [$ancillaryCost->invoice_id, $ancillaryCost]))
            ->assertOk()
            ->assertSee(__('Tax evidence'))
            ->assertSee('Freight invoice');
    }
}
