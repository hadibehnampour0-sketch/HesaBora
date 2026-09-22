<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Workstream U (dedicated adversarial cross-company attack pass, Epic Pack
 * 1). CRITICAL finding: getActiveCompany() (app/Helpers/helpers.php) fell
 * back to `Cookie::get('active-company-id')` -- Laravel's Cookie facade
 * reads this straight from the CURRENT request's raw, incoming cookie
 * (Illuminate\Support\Facades\Cookie::get() -> $app['request']->cookie()),
 * completely independent of DefaultCompany middleware's own membership
 * check. Cookie::forget() (what DefaultCompany calls when a cookie's
 * company fails that check) only queues an EXPIRING cookie for the
 * OUTGOING response -- it never touches the current request's already
 * -parsed cookie value. So the exact state DefaultCompany leaves things in
 * after rejecting a spoofed cookie (config('active-company-id') === null,
 * original cookie still present on the request) was precisely the state
 * that made this fallback trust the raw, attacker-chosen cookie anyway.
 *
 * Any authenticated user, regardless of actual company membership, could
 * set active-company-id to an arbitrary company id and every
 * FiscalYearScope-scoped model (Document, Invoice, Cheque, Payroll,
 * Transaction, Subject, and more) would read/write against THAT company
 * instead of one they belong to -- a full cross-tenant data breach via a
 * single cookie value, not merely a single-resource id-enumeration gap
 * like Workstream J's earlier findings.
 */
class ActiveCompanyCookieSpoofingTest extends TestCase
{
    use RefreshDatabase;

    public function test_spoofed_active_company_cookie_does_not_grant_access_to_a_company_the_user_does_not_belong_to(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);

        $actorA = User::factory()->create();
        $actorA->companies()->syncWithoutDetaching([$companyA->id]);

        $previousActiveCompany = config('active-company-id');
        config(['active-company-id' => $companyB->id]);
        $customerB = Customer::factory()->create();
        $documentB = Document::factory()->create(['company_id' => $companyB->id]);
        config(['active-company-id' => $previousActiveCompany]);

        // actorA is a member of companyA only. Spoof the cookie to
        // companyB's id -- DefaultCompany must reject it (companyB is not
        // among actorA's memberships) and getActiveCompany() must never
        // fall through to the raw, untrusted cookie value as a result.
        $response = $this->actingAs($actorA)
            ->withCookie('active-company-id', (string) $companyB->id)
            ->get(route('documents.show', $documentB));

        $response->assertNotFound();
    }

    public function test_spoofed_cookie_for_a_nonexistent_company_falls_back_safely(): void
    {
        $actorA = User::factory()->create();
        $actorA->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super-Admin']));
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $actorA->companies()->syncWithoutDetaching([$companyA->id]);

        $this->actingAs($actorA)
            ->withCookie('active-company-id', '999999')
            ->get(route('home'))
            ->assertOk();
    }
}
