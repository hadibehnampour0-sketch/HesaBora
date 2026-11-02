<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\NoActiveCompanyException;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FailClosedActiveCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_call_to_get_active_company_without_context_throws_no_active_company_exception(): void
    {
        config(['active-company-id' => null]);

        $this->expectException(NoActiveCompanyException::class);
        getActiveCompany();
    }

    public function test_positive_control_get_active_company_returns_configured_id(): void
    {
        config(['active-company-id' => 77]);

        $this->assertSame(77, getActiveCompany());
    }

    public function test_user_without_any_company_accessing_workspace_redirects_to_company_selection(): void
    {
        $userWithoutCompany = User::factory()->create();

        // No active company cookie, user belongs to 0 companies
        $response = $this->actingAs($userWithoutCompany)
            ->get(route('home'));

        $response->assertRedirect(route('companies.index'));
        $response->assertSessionHas('error');
    }

    public function test_api_request_without_active_company_context_returns_422_fail_closed(): void
    {
        $user = User::factory()->create();

        // Direct API call without company context
        $response = $this->actingAs($user)
            ->getJson('/api/v1/documents');

        $this->assertContains($response->status(), [400, 422, 404]);
    }

    public function test_user_with_stale_or_spoofed_cookie_cannot_read_another_companys_document(): void
    {
        $companyA = Company::create(['name' => 'Tenant A', 'fiscal_year' => 1405]);
        $companyB = Company::create(['name' => 'Tenant B', 'fiscal_year' => 1405]);

        $userA = User::factory()->create();
        $userA->companies()->syncWithoutDetaching([$companyA->id]);

        $docB = null;
        $this->withCompanyContext($companyB, function () use (&$docB, $companyB) {
            $docB = Document::factory()->create([
                'company_id' => $companyB->id,
                'title' => 'Confidential Tenant B Document',
            ]);
        });

        // User A attempts to view Doc B using Company B's cookie
        $response = $this->actingAs($userA)
            ->withCookie('active-company-id', (string) $companyB->id)
            ->get(route('documents.show', $docB));

        // Must fail closed (404 Not Found via FiscalYearScope or 403 Forbidden)
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_positive_control_user_can_read_their_own_company_document(): void
    {
        $companyA = Company::create(['name' => 'Tenant A', 'fiscal_year' => 1405]);
        $userA = User::factory()->create();
        $userA->companies()->syncWithoutDetaching([$companyA->id]);

        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($companyA->id);
        $userA->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'documents.show']));
        $registrar->setPermissionsTeamId(null);

        $docA = null;
        $this->withCompanyContext($companyA, function () use (&$docA, $companyA) {
            $docA = Document::factory()->create([
                'company_id' => $companyA->id,
                'title' => 'Tenant A Native Document',
            ]);
        });

        $response = $this->actingAs($userA)
            ->withCookie('active-company-id', (string) $companyA->id)
            ->get(route('documents.show', $docA));

        $response->assertOk();
        $response->assertSee('Tenant A Native Document');
    }

    private function withCompanyContext(Company $company, callable $callback): mixed
    {
        $previous = config('active-company-id');
        config(['active-company-id' => $company->id]);
        try {
            return $callback();
        } finally {
            config(['active-company-id' => $previous]);
        }
    }
}
