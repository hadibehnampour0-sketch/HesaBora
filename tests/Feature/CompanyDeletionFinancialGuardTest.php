<?php

namespace Tests\Feature;

use App\Enums\FinancialProvenance;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\PaymentAllocation;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\User;
use App\Services\FiscalYearService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * D-085 -- provenance-aware Company deletion. A Company may be deleted only
 * when every financially effective record it contains is demonstrably part
 * of its copied/imported baseline history (FinancialProvenance::BASELINE),
 * with no native/local financial activity (FinancialProvenance::NATIVE, the
 * safe default for every existing/legacy row and every ordinarily-created
 * Document) since. See Company::booted()'s `deleting` hook.
 */
class CompanyDeletionFinancialGuardTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    private User $user;

    private Company $company;

    /** Read by InvoiceTestHelper::createProduct(); switched per-test as needed. */
    private int $companyId;

    /** Read by InvoiceTestHelper::createInvoice(). */
    private Customer $customer;

    private int $nextInvoiceNumber = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);
        $this->importSubjects($this->company->id);
        $this->importConfigs($this->company->id);

        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'companies.destroy']));

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => (string) $this->company->id]);
        config(['active-company-id' => $this->company->id]);
        Cache::forever('active_company_id', $this->company->id);
        Cookie::queue('active-company-id', (string) $this->company->id);
        $_COOKIE['active-company-id'] = (string) $this->company->id;
    }

    /**
     * Sets up a source Company with real reference data and one approved
     * (NATIVE) Document, then copies it into a brand-new target Company via
     * the real production copy path -- the exact mechanism D-085's forensic
     * investigation traced.
     */
    private function copyIntoNewCompany(array $sections = ['documents']): Company
    {
        $source = Company::factory()->create();
        config(['active-company-id' => $source->id]);
        Cache::forever('active_company_id', $source->id);
        Cookie::queue('active-company-id', (string) $source->id);
        $_COOKIE['active-company-id'] = (string) $source->id;
        $source->users()->syncWithoutDetaching($this->user);

        $this->importSubjects($source->id);
        $this->importConfigs($source->id);

        Document::create([
            'company_id' => $source->id,
            'number' => 1,
            'date' => now()->toDateString(),
            'creator_id' => $this->user->id,
            'approved_at' => now(),
        ]);

        $target = FiscalYearService::createWithCopiedData(
            ['name' => 'Copy Target', 'fiscal_year' => (int) $source->fiscal_year + 1],
            $source->id,
            $sections
        );

        config(['active-company-id' => $target->id]);
        Cache::forever('active_company_id', $target->id);
        Cookie::queue('active-company-id', (string) $target->id);
        $_COOKIE['active-company-id'] = (string) $target->id;

        return $target;
    }

    private function customerInCompany(int $companyId): Customer
    {
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'g', 'company_id' => $companyId]);

        return Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $companyId]);
    }

    private function cashSubjectId(int $companyId): int
    {
        $cashBook = Subject::withoutGlobalScopes()->find((int) config('amir.cash_book'));
        $box = Subject::factory()->withParent($cashBook)->create(['name' => 'صندوق', 'company_id' => $companyId]);

        return (int) $box->id;
    }

    // --- A/B/E: harmless companies remain deletable exactly as before -------

    public function test_company_with_an_approved_document_cannot_be_deleted(): void
    {
        Document::factory()->create([
            'company_id' => $this->company->id,
            'approved_at' => now(),
        ]);

        $response = $this->delete(route('companies.destroy', $this->company));

        $response->assertRedirect(route('companies.index'));
        $response->assertSessionHasErrors([
            'company' => __('This company has locally-originated financial activity and cannot be deleted.'),
        ]);
        $this->assertDatabaseHas('companies', ['id' => $this->company->id]);
    }

    public function test_company_with_only_unapproved_documents_can_still_be_deleted(): void
    {
        Document::factory()->create([
            'company_id' => $this->company->id,
            'approved_at' => null,
        ]);

        $response = $this->delete(route('companies.destroy', $this->company));

        $response->assertRedirect(route('companies.index'));
        $response->assertSessionHas('success', __('Company deleted successfully.'));
        $this->assertDatabaseMissing('companies', ['id' => $this->company->id]);
    }

    public function test_company_with_no_documents_can_still_be_deleted(): void
    {
        $response = $this->delete(route('companies.destroy', $this->company));

        $response->assertRedirect(route('companies.index'));
        $response->assertSessionHas('success', __('Company deleted successfully.'));
        $this->assertDatabaseMissing('companies', ['id' => $this->company->id]);
    }

    public function test_company_with_only_non_financial_operational_data_can_still_be_deleted(): void
    {
        $this->customerInCompany($this->company->id);
        ProductGroup::factory()->withSubjects()->create(['name' => 'g', 'vat' => 10, 'company_id' => $this->company->id]);

        $response = $this->delete(route('companies.destroy', $this->company));

        $response->assertRedirect(route('companies.index'));
        $response->assertSessionHas('success', __('Company deleted successfully.'));
        $this->assertDatabaseMissing('companies', ['id' => $this->company->id]);
    }

    public function test_guard_checks_the_target_company_regardless_of_the_active_company_context(): void
    {
        $otherCompany = Company::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);
        $otherCompany->users()->syncWithoutDetaching($this->user);

        Document::withoutGlobalScopes()->create([
            'company_id' => $otherCompany->id,
            'number' => 1,
            'date' => now()->toDateString(),
            'creator_id' => $this->user->id,
            'approved_at' => now(),
        ]);

        // Active company context is $this->company, but we're deleting $otherCompany.
        $response = $this->delete(route('companies.destroy', $otherCompany));

        $response->assertSessionHasErrors([
            'company' => __('This company has locally-originated financial activity and cannot be deleted.'),
        ]);
        $this->assertDatabaseHas('companies', ['id' => $otherCompany->id]);
    }

    // --- C: copied/imported baseline financial history only -----------------

    public function test_a_freshly_copied_company_with_only_baseline_history_can_be_deleted(): void
    {
        $target = $this->copyIntoNewCompany(['documents']);

        $copiedDoc = Document::withoutGlobalScopes()->where('company_id', $target->id)->sole();
        $this->assertSame(FinancialProvenance::BASELINE, $copiedDoc->provenance);
        $this->assertNotNull($copiedDoc->approved_at);

        $target->delete();

        $this->assertDatabaseMissing('companies', ['id' => $target->id]);
    }

    /**
     * D-085 hardening (owner-required boundary test A): _importDocuments()
     * only ever creates the Document rows themselves, but _importPayments()
     * copies real Payment rows that reference those (now BASELINE) mapped
     * documents. This proves the claim "baseline-only is deletable" holds
     * even when a Payment -- not just a bare Document -- is part of the
     * copied history, through the real production copy path (subjects,
     * customers, products, warehouses, documents, invoices all copied, so
     * _importPayments() has everything it needs to actually run).
     */
    public function test_a_copied_company_with_a_copied_payment_and_no_native_activity_can_be_deleted(): void
    {
        $source = Company::factory()->create();
        config(['active-company-id' => $source->id]);
        Cache::forever('active_company_id', $source->id);
        Cookie::queue('active-company-id', (string) $source->id);
        $_COOKIE['active-company-id'] = (string) $source->id;
        $source->users()->syncWithoutDetaching($this->user);

        $this->importSubjects($source->id);
        $this->importConfigs($source->id);
        ProductGroup::factory()->withSubjects()->create(['name' => 'g', 'vat' => 10, 'company_id' => $source->id]);
        $this->companyId = $source->id;
        $this->customer = $this->customerInCompany($source->id);

        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber);
        $sell = $this->findInvoice($this->sell([$this->productItem($product, 1, 1000)], true, ++$this->nextInvoiceNumber)['invoice']->id);
        app(PaymentService::class)->createPayment($this->user, $sell, [
            'amount' => 1000,
            'subject_id' => $this->cashSubjectId($source->id),
        ]);
        $sourcePaymentCount = \App\Models\Payment::withoutGlobalScopes()->where('invoice_id', $sell->id)->count();
        $this->assertSame(1, $sourcePaymentCount, 'Precondition: the source invoice has exactly one Payment.');

        $target = FiscalYearService::createWithCopiedData(
            ['name' => 'Copy Target', 'fiscal_year' => (int) $source->fiscal_year + 1],
            $source->id,
            ['subjects', 'customers', 'products', 'warehouses', 'documents', 'invoices']
        );
        config(['active-company-id' => $target->id]);
        Cache::forever('active_company_id', $target->id);
        Cookie::queue('active-company-id', (string) $target->id);
        $_COOKIE['active-company-id'] = (string) $target->id;

        $copiedPayments = \App\Models\Payment::withoutGlobalScopes()
            ->whereHas('document', fn ($q) => $q->where('company_id', $target->id))
            ->get();
        $this->assertCount(1, $copiedPayments, 'The Payment must have been copied along with the baseline Documents/Invoices.');

        $copiedDocuments = Document::withoutGlobalScopes()->where('company_id', $target->id)->get();
        $this->assertGreaterThan(0, $copiedDocuments->count());
        $this->assertTrue(
            $copiedDocuments->every(fn (Document $doc) => $doc->provenance === FinancialProvenance::BASELINE),
            'Every copied Document must be BASELINE.'
        );
        $this->assertSame(
            0,
            PaymentAllocation::whereHas('payment', fn ($q) => $q->where('company_id', $target->id))->count(),
            'No native Allocation should exist -- this is the pure baseline-only case.'
        );

        $target->delete();

        $this->assertDatabaseMissing('companies', ['id' => $target->id]);
    }

    /**
     * D-085 hardening (owner-required boundary test B): a BASELINE approved
     * Invoice's own document must never remain BASELINE after a native
     * financial edit. InvoiceService::updateInvoice()'s already-approved-edit
     * branch deletes the old Document entirely and creates a brand new one
     * via createDocumentFromInvoiceItems() -- this proves that new Document
     * lands as NATIVE (the column default), never inheriting BASELINE, and
     * that the Company becomes correctly undeletable the moment this
     * happens. If this test ever fails, D-085 has a real bypass: a copied
     * Company could stay "deletable" forever while its financial content
     * was silently rewritten locally.
     */
    public function test_editing_a_copied_baseline_invoice_produces_a_native_document_and_blocks_deletion(): void
    {
        $source = Company::factory()->create();
        config(['active-company-id' => $source->id]);
        Cache::forever('active_company_id', $source->id);
        Cookie::queue('active-company-id', (string) $source->id);
        $_COOKIE['active-company-id'] = (string) $source->id;
        $source->users()->syncWithoutDetaching($this->user);

        $this->importSubjects($source->id);
        $this->importConfigs($source->id);
        ProductGroup::factory()->withSubjects()->create(['name' => 'g', 'vat' => 10, 'company_id' => $source->id]);
        $this->companyId = $source->id;
        $this->customer = $this->customerInCompany($source->id);

        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 5, 500)], true, ++$this->nextInvoiceNumber);
        $sell = $this->findInvoice($this->sell([$this->productItem($product, 2, 1000)], true, ++$this->nextInvoiceNumber)['invoice']->id);

        $target = FiscalYearService::createWithCopiedData(
            ['name' => 'Copy Target', 'fiscal_year' => (int) $source->fiscal_year + 1],
            $source->id,
            ['subjects', 'customers', 'products', 'warehouses', 'documents', 'invoices']
        );
        config(['active-company-id' => $target->id]);
        Cache::forever('active_company_id', $target->id);
        Cookie::queue('active-company-id', (string) $target->id);
        $_COOKIE['active-company-id'] = (string) $target->id;

        $copiedInvoice = Invoice::withoutGlobalScopes()
            ->where('company_id', $target->id)
            ->where('invoice_type', $sell->invoice_type)
            ->sole();
        $oldDocumentId = $copiedInvoice->document_id;
        $this->assertNotNull($oldDocumentId);
        $this->assertSame(FinancialProvenance::BASELINE, Document::withoutGlobalScopes()->findOrFail($oldDocumentId)->provenance);

        // Sanity: still baseline-only, still deletable before the edit.
        $preEditCompanyId = $target->id;
        $this->assertTrue(
            Document::withoutGlobalScopes()->where('company_id', $preEditCompanyId)->whereNotNull('approved_at')
                ->where('provenance', FinancialProvenance::NATIVE)->doesntExist()
        );

        $copiedProduct = $this->findProduct((int) $copiedInvoice->items->first()->itemable_id);
        $this->companyId = $target->id;
        InvoiceService::updateInvoice(
            $copiedInvoice->id,
            [
                'title' => $copiedInvoice->title,
                'date' => $copiedInvoice->date->toDateString(),
                'invoice_type' => $copiedInvoice->invoice_type,
                'customer_id' => $copiedInvoice->customer_id,
                'document_number' => $copiedInvoice->document?->number,
                'number' => $copiedInvoice->number,
                'description' => $copiedInvoice->description,
                'subtraction' => $copiedInvoice->subtraction ?? 0,
                'warehouse_id' => $copiedInvoice->warehouse_id,
            ],
            [$this->productItem($copiedProduct, 1, 1200)],
            true
        );

        $this->assertNull(Document::withoutGlobalScopes()->find($oldDocumentId), 'The old BASELINE document must be gone, not silently mutated.');

        $newDocument = Invoice::withoutGlobalScopes()->findOrFail($copiedInvoice->id)->document;
        $this->assertNotNull($newDocument);
        $this->assertNotSame($oldDocumentId, $newDocument->id);
        $this->assertSame(
            FinancialProvenance::NATIVE,
            $newDocument->provenance,
            'A native financial edit must never leave the resulting Document as BASELINE.'
        );

        $response = $this->delete(route('companies.destroy', $target));
        $response->assertSessionHasErrors([
            'company' => __('This company has locally-originated financial activity and cannot be deleted.'),
        ]);
        $this->assertDatabaseHas('companies', ['id' => $target->id]);
    }

    // --- D/I: baseline + new native activity is rejected ---------------------

    public function test_a_copied_company_with_a_new_native_payment_cannot_be_deleted(): void
    {
        $target = $this->copyIntoNewCompany(['documents']);
        $this->importSubjects($target->id);
        $this->importConfigs($target->id);
        $customer = $this->customerInCompany($target->id);

        app(PaymentService::class)->createCreditPayment($this->user, $customer, [
            'amount' => 500,
            'subject_id' => $this->cashSubjectId($target->id),
        ]);

        $this->expectException(ValidationException::class);

        try {
            $target->delete();
        } finally {
            $this->assertDatabaseHas('companies', ['id' => $target->id]);
        }
    }

    public function test_a_copied_company_with_a_new_native_allocation_cannot_be_deleted(): void
    {
        $target = $this->copyIntoNewCompany(['documents']);
        $this->importSubjects($target->id);
        $this->importConfigs($target->id);
        ProductGroup::factory()->withSubjects()->create(['name' => 'g', 'vat' => 10, 'company_id' => $target->id]);

        // InvoiceTestHelper reads $this->companyId/$this->customer -- point
        // them at the freshly-copied target for this test only. Active
        // company context (cookie/cache) was already switched to $target
        // inside copyIntoNewCompany().
        $this->companyId = $target->id;
        $this->customer = $this->customerInCompany($target->id);

        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber);
        $sell = $this->findInvoice($this->sell([$this->productItem($product, 1, 1000)], true, ++$this->nextInvoiceNumber)['invoice']->id);

        $payment = app(PaymentService::class)->createCreditPayment($this->user, $this->customer, [
            'amount' => 1000,
            'subject_id' => $this->cashSubjectId($target->id),
        ]);
        app(PaymentService::class)->allocatePayment($this->user, $payment, $sell, 1000);

        $this->expectException(ValidationException::class);

        try {
            $target->delete();
        } finally {
            $this->assertDatabaseHas('companies', ['id' => $target->id]);
        }
    }

    public function test_a_company_with_mixed_baseline_and_native_documents_cannot_be_deleted(): void
    {
        $target = $this->copyIntoNewCompany(['documents']);

        // A second, separately (natively) created approved Document, right
        // inside the freshly-copied target company.
        Document::create([
            'company_id' => $target->id,
            'number' => 999,
            'date' => now()->toDateString(),
            'creator_id' => $this->user->id,
            'approved_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        try {
            $target->delete();
        } finally {
            $this->assertDatabaseHas('companies', ['id' => $target->id]);
        }
    }

    // --- F/G: native activity and legacy/unknown provenance are rejected ----

    public function test_a_native_payment_alone_prevents_deletion(): void
    {
        $customer = $this->customerInCompany($this->company->id);

        app(PaymentService::class)->createCreditPayment($this->user, $customer, [
            'amount' => 500,
            'subject_id' => $this->cashSubjectId($this->company->id),
        ]);

        $response = $this->delete(route('companies.destroy', $this->company));

        $response->assertSessionHasErrors([
            'company' => __('This company has locally-originated financial activity and cannot be deleted.'),
        ]);
        $this->assertDatabaseHas('companies', ['id' => $this->company->id]);
    }

    /**
     * A row with no explicit provenance (e.g. one that predates D-085 and
     * was never backfilled with an application-level value) relies purely
     * on the migration's own column default. This proves the safe default
     * itself -- not application code -- is what protects legacy data.
     */
    public function test_a_legacy_approved_document_with_no_explicit_provenance_prevents_deletion(): void
    {
        DB::table('documents')->insert([
            'company_id' => $this->company->id,
            'number' => 1,
            'date' => now()->toDateString(),
            'creator_id' => $this->user->id,
            'approved_at' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
            // Deliberately no 'provenance' key -- the column's own DEFAULT
            // (NATIVE) must apply, exactly as it would for any pre-D-085 row.
        ]);

        $response = $this->delete(route('companies.destroy', $this->company));

        $response->assertSessionHasErrors([
            'company' => __('This company has locally-originated financial activity and cannot be deleted.'),
        ]);
        $this->assertDatabaseHas('companies', ['id' => $this->company->id]);
    }

    // --- No partial deletion on rejection ------------------------------------

    public function test_a_rejected_company_deletion_leaves_its_documents_and_users_intact(): void
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'approved_at' => now(),
        ]);

        try {
            $this->company->delete();
        } catch (ValidationException) {
            // expected
        }

        $this->assertDatabaseHas('companies', ['id' => $this->company->id]);
        $this->assertDatabaseHas('documents', ['id' => $document->id]);
        $this->assertDatabaseHas('company_user', ['company_id' => $this->company->id, 'user_id' => $this->user->id]);
    }

    // --- Batch 4 (Financial Authority / Destructive-Action Final Audit) -----

    /**
     * `FiscalYearService::_importCustomers()` creates each copied Customer
     * via `saveQuietly()`, which suppresses `HasSyncEnvelope`'s own
     * `creating` hook -- the exact same bug class already found and fixed
     * for `_importInvoices()` during D-085's hardening (uuid has no DB
     * default, unlike sync_version/sync_status). A copied Customer with
     * `uuid = NULL` crashes on its first later `update()` via
     * `SyncsToOutbox`'s NOT NULL `sync_outbox.entity_uuid` constraint.
     */
    public function test_a_copied_customer_can_be_updated_without_crashing(): void
    {
        $source = Company::factory()->create();
        config(['active-company-id' => $source->id]);
        Cache::forever('active_company_id', $source->id);
        Cookie::queue('active-company-id', (string) $source->id);
        $_COOKIE['active-company-id'] = (string) $source->id;
        $source->users()->syncWithoutDetaching($this->user);

        $this->importSubjects($source->id);
        $this->importConfigs($source->id);
        $this->customerInCompany($source->id);

        $target = FiscalYearService::createWithCopiedData(
            ['name' => 'Copy Target', 'fiscal_year' => (int) $source->fiscal_year + 1],
            $source->id,
            ['subjects', 'customers']
        );

        config(['active-company-id' => $target->id]);
        Cache::forever('active_company_id', $target->id);
        Cookie::queue('active-company-id', (string) $target->id);
        $_COOKIE['active-company-id'] = (string) $target->id;

        $copiedCustomer = Customer::where('company_id', $target->id)->firstOrFail();
        $this->assertNotNull($copiedCustomer->uuid, 'A copied customer must not have a null uuid.');

        $copiedCustomer->update(['name' => 'Renamed Customer']);

        $this->assertDatabaseHas('customers', ['id' => $copiedCustomer->id, 'name' => 'Renamed Customer']);
    }
}
