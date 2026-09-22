<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\IdempotencyKey;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * P6 (API v1 Platform). ApiTest.php already exercises the underlying
 * business logic (permission checks, company scoping, validation) via the
 * unversioned /api/* routes -- these controllers are the exact same code
 * the v1 adapter controllers delegate to (App\Http\Controllers\Api\V1\*
 * -> ApiResponds::fromLegacy()), so this file focuses only on what v1
 * actually adds: the standardized envelope shape and the Idempotency-Key
 * middleware, not re-proving validation/permission rules already covered.
 */
class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);

        $permissions = [
            'api.access',
            'companies.index',
            'hr.employees.index',
            'hr.employees.store',
        ];

        foreach ($permissions as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }

        $this->token = $this->user->createToken('device', $permissions)->plainTextToken;
    }

    protected function apiHeaders(array $extra = []): array
    {
        return array_merge(['Authorization' => 'Bearer '.$this->token], $extra);
    }

    protected function companyApiV1Url(string $path): string
    {
        return '/api/v1/companies/'.$this->company->id.$path;
    }

    public function test_v1_companies_endpoint_uses_the_standard_envelope(): void
    {
        $response = $this->getJson('/api/v1/companies', $this->apiHeaders());

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'fiscal_year', 'currency', 'closed_at']]]);
    }

    public function test_v1_employee_store_uses_the_standard_envelope(): void
    {
        $workSite = WorkSite::factory()->create(['company_id' => $this->company->id]);
        $workShift = WorkShift::factory()->create(['company_id' => $this->company->id]);

        $response = $this->postJson($this->companyApiV1Url('/employees'), [
            'code' => 'EMP-V1-1',
            'first_name' => 'Sara',
            'last_name' => 'Test',
            'nationality' => 'iranian',
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
        ], $this->apiHeaders());

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'code', 'first_name', 'last_name']]);

        $this->assertDatabaseHas('employees', ['code' => 'EMP-V1-1', 'company_id' => $this->company->id]);
    }

    public function test_v1_employee_store_still_enforces_token_scoping(): void
    {
        $limitedToken = $this->user->createToken('limited', ['companies.index'])->plainTextToken;

        $this->postJson($this->companyApiV1Url('/employees'), [
            'code' => 'EMP-V1-2',
        ], ['Authorization' => 'Bearer '.$limitedToken])->assertForbidden();
    }

    public function test_idempotency_key_replays_the_original_response_instead_of_creating_a_second_employee(): void
    {
        $workSite = WorkSite::factory()->create(['company_id' => $this->company->id]);
        $workShift = WorkShift::factory()->create(['company_id' => $this->company->id]);

        $payload = [
            'code' => 'EMP-IDEMP-1',
            'first_name' => 'Reza',
            'last_name' => 'Test',
            'nationality' => 'iranian',
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
        ];

        $headers = $this->apiHeaders(['Idempotency-Key' => 'key-abc-123']);

        $first = $this->postJson($this->companyApiV1Url('/employees'), $payload, $headers);
        $first->assertCreated();

        $second = $this->postJson($this->companyApiV1Url('/employees'), $payload, $headers);
        $second->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Employee::where('code', 'EMP-IDEMP-1')->count());
    }

    public function test_idempotency_key_reused_with_a_different_body_is_rejected(): void
    {
        $workSite = WorkSite::factory()->create(['company_id' => $this->company->id]);
        $workShift = WorkShift::factory()->create(['company_id' => $this->company->id]);

        $headers = $this->apiHeaders(['Idempotency-Key' => 'key-conflict-1']);

        $this->postJson($this->companyApiV1Url('/employees'), [
            'code' => 'EMP-CONFLICT-1',
            'first_name' => 'A',
            'last_name' => 'B',
            'nationality' => 'iranian',
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
        ], $headers)->assertCreated();

        $this->postJson($this->companyApiV1Url('/employees'), [
            'code' => 'EMP-CONFLICT-2',
            'first_name' => 'C',
            'last_name' => 'D',
            'nationality' => 'iranian',
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
        ], $headers)->assertStatus(409);

        $this->assertSame(1, Employee::where('code', 'EMP-CONFLICT-1')->count());
        $this->assertSame(0, Employee::where('code', 'EMP-CONFLICT-2')->count());
    }

    public function test_requests_without_an_idempotency_key_are_never_deduplicated(): void
    {
        $workSite = WorkSite::factory()->create(['company_id' => $this->company->id]);
        $workShift = WorkShift::factory()->create(['company_id' => $this->company->id]);

        $payload = [
            'first_name' => 'NoKey',
            'last_name' => 'Test',
            'nationality' => 'iranian',
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
        ];

        $this->postJson($this->companyApiV1Url('/employees'), $payload + ['code' => 'EMP-NOKEY-1'], $this->apiHeaders())
            ->assertCreated();
        $this->postJson($this->companyApiV1Url('/employees'), $payload + ['code' => 'EMP-NOKEY-2'], $this->apiHeaders())
            ->assertCreated();

        $this->assertSame(2, Employee::whereIn('code', ['EMP-NOKEY-1', 'EMP-NOKEY-2'])->count());
        $this->assertSame(0, IdempotencyKey::count());
    }

    public function test_idempotency_key_is_scoped_per_user_not_shared_across_tokens(): void
    {
        $otherUser = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($otherUser);
        foreach (['api.access', 'hr.employees.store', 'hr.employees.index'] as $permission) {
            $otherUser->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
        $otherToken = $otherUser->createToken('device2', ['api.access', 'hr.employees.store'])->plainTextToken;

        $workSite = WorkSite::factory()->create(['company_id' => $this->company->id]);
        $workShift = WorkShift::factory()->create(['company_id' => $this->company->id]);

        $payload = [
            'first_name' => 'Shared',
            'last_name' => 'Key',
            'nationality' => 'iranian',
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
        ];

        $this->postJson($this->companyApiV1Url('/employees'), $payload + ['code' => 'EMP-USER-1'], $this->apiHeaders(['Idempotency-Key' => 'shared-key']))
            ->assertCreated();

        // Sanctum's RequestGuard caches its resolved user for the lifetime of
        // the guard instance (Illuminate\Auth\RequestGuard::user()) -- a
        // second simulated request with a different Bearer token within the
        // SAME test method would otherwise silently keep authenticating as
        // the FIRST user. Laravel's own testing helper for this is
        // Auth::forgetGuards(), which forces the next request to resolve
        // the guard (and therefore the user) fresh from the new token.
        \Illuminate\Support\Facades\Auth::forgetGuards();

        $this->postJson($this->companyApiV1Url('/employees'), $payload + ['code' => 'EMP-USER-2'], [
            'Authorization' => 'Bearer '.$otherToken,
            'Idempotency-Key' => 'shared-key',
        ])->assertCreated();

        $this->assertSame(2, Employee::whereIn('code', ['EMP-USER-1', 'EMP-USER-2'])->count());
    }
}
