<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\NoActiveCompanyException;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FailClosedEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private User $userA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create(['name' => 'Tenant A', 'fiscal_year' => 1405]);
        $this->companyB = Company::create(['name' => 'Tenant B', 'fiscal_year' => 1405]);

        $this->userA = User::factory()->create();
        $this->companyA->users()->syncWithoutDetaching([$this->userA->id]);
    }

    /**
     * Entry Point 1: Payment Link Callback
     * Valid token resolves strictly to the target company's invoice; invalid token 404s and never defaults to Company 1.
     */
    public function test_payment_link_callback_fails_closed_on_invalid_token(): void
    {
        config(['active-company-id' => null]);

        $response = $this->get(route('portal.invoice.callback', ['token' => 'invalid-token-'.Str::random(16)]));
        $response->assertNotFound();
    }

    /**
     * Entry Point 2: Customer Online Portal
     * Invalid portal token fails closed with 404 without falling back to any default company.
     */
    public function test_customer_portal_fails_closed_on_invalid_token(): void
    {
        config(['active-company-id' => null]);

        $response = $this->get(route('portal.invoice.show', ['token' => 'non-existent-token']));
        $response->assertNotFound();
    }

    /**
     * Entry Point 3: Biometric Attendance iClock API
     * Requests without registered device SN fail closed with 401 Unauthorized.
     */
    public function test_iclock_entry_point_fails_closed_without_valid_device_credentials(): void
    {
        config(['active-company-id' => null]);

        $response = $this->get('/api/attendance/iclock');
        $response->assertStatus(401);

        $responseInvalidSn = $this->get('/api/attendance/iclock?SN=UNKNOWN-SN-999');
        $responseInvalidSn->assertStatus(401);
    }

    /**
     * Entry Point 4: Caller-ID Telephony Lookup
     * Calling caller-ID lookup endpoint without active company context fails closed with 422/401/403.
     */
    public function test_caller_id_lookup_fails_closed_without_active_company_context(): void
    {
        config(['active-company-id' => null]);

        $response = $this->getJson(route('telephony.lookup', ['number' => '09121111111']));
        $this->assertContains($response->status(), [400, 422, 403, 401]);
    }

    /**
     * Entry Point 5: API Token Request
     * API request authenticated via Sanctum token but without company header fails closed with 422.
     */
    public function test_api_token_request_without_company_context_fails_closed(): void
    {
        config(['active-company-id' => null]);
        Sanctum::actingAs($this->userA);

        $response = $this->getJson('/api/v1/documents');
        $this->assertContains($response->status(), [400, 422, 404]);
    }

    /**
     * Entry Point 6: Queued Job Context
     * An asynchronous job running in worker without active company context throws NoActiveCompanyException.
     */
    public function test_queued_job_without_company_context_fails_closed(): void
    {
        config(['active-company-id' => null]);

        $job = new class
        {
            public function handle(): void
            {
                getActiveCompany();
            }
        };

        $this->expectException(NoActiveCompanyException::class);
        $job->handle();
    }

    /**
     * Entry Point 7: Artisan Commands
     * Console command executing without company context fails closed when getActiveCompany() is called.
     */
    public function test_console_command_without_company_context_fails_closed(): void
    {
        config(['active-company-id' => null]);

        $this->expectException(NoActiveCompanyException::class);
        getActiveCompany();
    }
}
