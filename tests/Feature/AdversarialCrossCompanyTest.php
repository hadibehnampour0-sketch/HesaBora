<?php

namespace Tests\Feature;

use App\Enums\ChequeType;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Organization Authorization & Workflow Governance phase, Workstream U
 * (dedicated adversarial cross-company attack pass, Epic Pack 1). Unlike
 * CrossCompanyResourceAccessTest (which proves 7 specific CONFIRMED-BROKEN
 * gaps are now fixed), this suite adversarially re-verifies the STRUCTURAL
 * protection Workstreams O/P claimed for models carrying FiscalYearScope
 * (Document, Invoice, Cheque, Payroll) -- "the scope exists in the model" is
 * a code-reading claim, not proof; every test here drives a real HTTP
 * request as a Company A actor against a Company B resource and checks the
 * actual response, following this mission's own established rule that
 * structural safety claims must be empirically re-verified, not trusted.
 */
class AdversarialCrossCompanyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function withActiveCompany(Company $company, \Closure $callback)
    {
        $previous = config('active-company-id');
        config(['active-company-id' => $company->id]);

        try {
            return $callback();
        } finally {
            config(['active-company-id' => $previous]);
        }
    }

    private function actorInCompany(Company $company): User
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

    private function makeDocumentIn(Company $company): Document
    {
        return $this->withActiveCompany($company, fn () => Document::factory()->create(['company_id' => $company->id]));
    }

    private function makeInvoiceIn(Company $company): Invoice
    {
        // Invoice::booted()'s creating() hook unconditionally overwrites
        // company_id with getActiveCompany() (unlike Cheque's ??=), so the
        // active-company context must genuinely be $company for the whole
        // create() call, not just passed as an attribute.
        return $this->withActiveCompany($company, function () use ($company) {
            $customer = Customer::factory()->create();

            return Invoice::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'number' => random_int(1, 999999),
                'date' => now(),
                'invoice_type' => 1,
                'status' => 1,
                'amount' => 1000,
                'vat' => 0,
            ]);
        });
    }

    private function makeChequeIn(Company $company): Cheque
    {
        $customer = $this->withActiveCompany($company, fn () => Customer::factory()->create());

        return Cheque::create([
            'company_id' => $company->id,
            'amount' => 1000,
            'write_date' => now(),
            'due_date' => now()->addMonth(),
            'sayad_number' => (string) random_int(1000000000000000, 9999999999999999),
            'direction' => ChequeType::RECEIVABLE,
            'purpose' => ChequeType::SETTLEMENT,
            'status' => ChequeType::REGISTERED,
            'customer_id' => $customer->id,
        ]);
    }

    public function test_company_a_cannot_view_edit_update_destroy_or_print_company_bs_document(): void
    {
        // StoreTransactionRequest's date field goes through
        // convertToGregorian(), which treats the input as Jalali when the
        // app locale is 'fa' (the default) -- a plain Gregorian
        // now()->toDateString() would be misparsed. Matches the same
        // app()->setLocale('en') convention DocumentImmutabilityTest uses.
        app()->setLocale('en');

        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);
        $documentB = $this->makeDocumentIn($companyB);

        $client = $this->asCompany($companyA, $actorA);

        $client->get(route('documents.show', $documentB))->assertNotFound();
        $client->get(route('documents.print', $documentB))->assertNotFound();
        // edit() looks up manually and redirects with a flash error rather
        // than a hard 404 (see DocumentController::edit()) -- still correct
        // in effect: the document is never shown to Company A.
        $client->get(route('documents.edit', $documentB))
            ->assertRedirect(route('documents.index'))
            ->assertSessionHas('error');
        $client->put(route('documents.update', $documentB), ['date' => now()->toDateString()])->assertNotFound();
        $client->delete(route('documents.destroy', $documentB))->assertNotFound();
        $client->get(route('documents.duplicate', $documentB))->assertNotFound();

        $this->assertDatabaseHas('documents', ['id' => $documentB->id]);
    }

    public function test_company_a_cannot_view_edit_update_or_destroy_company_bs_invoice(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);
        $invoiceB = $this->makeInvoiceIn($companyB);

        $client = $this->asCompany($companyA, $actorA);

        $client->get(route('invoices.show', $invoiceB))->assertNotFound();
        $client->get(route('invoices.edit', $invoiceB))->assertNotFound();
        $client->put(route('invoices.update', $invoiceB), [])->assertNotFound();
        $client->delete(route('invoices.destroy', $invoiceB))->assertNotFound();
        $client->get(route('invoices.conflicts', $invoiceB))->assertNotFound();

        $this->assertDatabaseHas('invoices', ['id' => $invoiceB->id]);
    }

    public function test_company_a_cannot_view_edit_update_or_destroy_company_bs_cheque(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);
        $chequeB = $this->makeChequeIn($companyB);

        $client = $this->asCompany($companyA, $actorA);

        $client->get(route('cheques.show', $chequeB))->assertNotFound();
        $client->get(route('cheques.edit', $chequeB))->assertNotFound();
        $client->put(route('cheques.update', $chequeB), [])->assertNotFound();
        $client->delete(route('cheques.destroy', $chequeB))->assertNotFound();

        $this->assertDatabaseHas('cheques', ['id' => $chequeB->id]);
    }

    public function test_company_a_cannot_view_or_download_company_bs_attached_document_file(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);
        $documentB = $this->makeDocumentIn($companyB);
        $fileB = DocumentFile::factory()->withDocument($documentB)->create();

        $client = $this->asCompany($companyA, $actorA);

        // Company A doesn't even have a valid $document to bind against
        // (documentB is invisible to it), so these must 404 before the
        // controller's own abort_unless() ownership check ever runs.
        $client->get(route('documents.files.view', [$documentB, $fileB]))->assertNotFound();
        $client->get(route('documents.files.download', [$documentB, $fileB]))->assertNotFound();
    }

    public function test_payroll_list_does_not_leak_another_companys_rows(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);

        $employeeB = $this->withActiveCompany($companyB, fn () => Employee::factory()->create(['company_id' => $companyB->id, 'first_name' => 'Secret', 'last_name' => 'EmployeeB']));
        Payroll::create([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'year' => 1403,
            'month' => 1,
            'status' => 1,
        ]);

        $this->asCompany($companyA, $actorA)
            ->get(route('salary.payrolls.index'))
            ->assertOk()
            ->assertDontSee('EmployeeB');
    }

    /**
     * Distinct attack vector from every test above: instead of enumerating
     * another company's resource ids (blocked by FiscalYearScope), this
     * attempts to spoof the ACTIVE COMPANY CONTEXT ITSELF by setting the
     * active-company-id cookie to a company the actor is not a member of.
     * DefaultCompany middleware must reject this and fall back to the
     * actor's own company, never granting the spoofed context.
     */
    public function test_setting_the_active_company_cookie_to_a_company_not_a_member_of_is_rejected(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);
        $documentB = $this->makeDocumentIn($companyB);

        // actorA is NOT a member of companyB -- spoof the cookie anyway.
        $this->actingAs($actorA)
            ->withCookie('active-company-id', (string) $companyB->id)
            ->get(route('documents.show', $documentB))
            ->assertNotFound();

        $this->assertNotEquals($companyB->id, config('active-company-id'));
    }
}
