<?php

namespace Tests\Feature;

use App\Enums\AnalyticDimensionType;
use App\Models\AnalyticDimension;
use App\Models\Company;
use App\Models\Document;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AnalyticDimensionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * P3.1 (Analytic Dimensions), Epic Pack 3. Covers CRUD, the delete-preserves
 * -audit-trail guard, transaction tagging, the subject-breakdown report, and
 * cross-company isolation -- built in from the start using the exact lesson
 * from CRITICAL finding #14 (Transaction has no FiscalYearScope, so any new
 * lookup by a Transaction id needs its own explicit ownership check).
 */
class AnalyticDimensionTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected AnalyticDimensionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        config(['active-company-id' => $this->company->id]);
        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);
        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->company->id]);
        $this->service = app(AnalyticDimensionService::class);
    }

    private function makeTransaction(): Transaction
    {
        $document = Document::factory()->create();
        $subject = Subject::create(['company_id' => $this->company->id, 'name' => 'Subject', 'code' => 'S'.random_int(1000, 999999), 'parent_id' => null]);

        return Transaction::create([
            'document_id' => $document->id,
            'subject_id' => $subject->id,
            'value' => 1000,
            'desc' => 'test',
        ]);
    }

    public function test_dimension_can_be_created_updated_and_belongs_to_the_active_company(): void
    {
        $dimension = $this->service->create([
            'type' => AnalyticDimensionType::COST_CENTER->value,
            'code' => 'CC-01',
            'name' => 'Marketing',
        ]);

        $this->assertSame($this->company->id, $dimension->company_id);
        $this->assertSame(AnalyticDimensionType::COST_CENTER, $dimension->type);

        $updated = $this->service->update($dimension, ['name' => 'Marketing & Sales']);
        $this->assertSame('Marketing & Sales', $updated->name);
    }

    public function test_dimension_with_no_tagged_transactions_can_be_deleted(): void
    {
        $dimension = $this->service->create(['type' => AnalyticDimensionType::BRANCH->value, 'code' => 'B-01', 'name' => 'Tehran']);

        $this->service->delete($dimension);

        $this->assertNull(AnalyticDimension::find($dimension->id));
    }

    public function test_dimension_tagged_on_a_transaction_cannot_be_deleted(): void
    {
        $dimension = $this->service->create(['type' => AnalyticDimensionType::PROJECT->value, 'code' => 'P-01', 'name' => 'Project X']);
        $transaction = $this->makeTransaction();
        $this->service->syncOnTransaction($transaction, [$dimension->id]);

        $this->expectException(ValidationException::class);

        try {
            $this->service->delete($dimension);
        } finally {
            $this->assertNotNull(AnalyticDimension::find($dimension->id));
        }
    }

    public function test_syncing_dimensions_on_a_transaction_and_the_subject_breakdown_report(): void
    {
        $branch = $this->service->create(['type' => AnalyticDimensionType::BRANCH->value, 'code' => 'B-01', 'name' => 'Tehran']);
        $project = $this->service->create(['type' => AnalyticDimensionType::PROJECT->value, 'code' => 'P-01', 'name' => 'Project X']);

        $transactionA = $this->makeTransaction();
        $transactionB = $this->makeTransaction();

        $this->service->syncOnTransaction($transactionA, [$branch->id, $project->id]);
        $this->service->syncOnTransaction($transactionB, [$branch->id]);

        $this->assertCount(2, $transactionA->fresh()->analyticDimensions);
        $this->assertCount(1, $transactionB->fresh()->analyticDimensions);

        $breakdown = $this->service->subjectBreakdown($branch);
        $this->assertEqualsWithDelta(2000.0, $breakdown->sum('total'), 0.01);

        $projectBreakdown = $this->service->subjectBreakdown($project);
        $this->assertEqualsWithDelta(1000.0, $projectBreakdown->sum('total'), 0.01);
    }

    public function test_re_syncing_a_transaction_replaces_its_previous_dimension_set(): void
    {
        $branch = $this->service->create(['type' => AnalyticDimensionType::BRANCH->value, 'code' => 'B-01', 'name' => 'Tehran']);
        $project = $this->service->create(['type' => AnalyticDimensionType::PROJECT->value, 'code' => 'P-01', 'name' => 'Project X']);
        $transaction = $this->makeTransaction();

        $this->service->syncOnTransaction($transaction, [$branch->id]);
        $this->service->syncOnTransaction($transaction, [$project->id]);

        $ids = $transaction->fresh()->analyticDimensions->pluck('id')->all();
        $this->assertSame([$project->id], $ids);
    }

    // --- Cross-company isolation -------------------------------------

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

    public function test_company_a_cannot_update_delete_or_report_company_bs_dimension(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);

        $dimensionB = $this->withActiveCompany($companyB, fn () => $this->service->create([
            'type' => AnalyticDimensionType::SALES_CHANNEL->value,
            'code' => 'CH-01',
            'name' => 'Online',
        ]));

        $client = $this->asCompany($companyA, $actorA);
        $client->put(route('analytic-dimensions.update', $dimensionB), ['name' => 'Hacked'])->assertNotFound();
        $client->delete(route('analytic-dimensions.destroy', $dimensionB))->assertNotFound();
        $client->get(route('analytic-dimensions.report', $dimensionB))->assertNotFound();

        $this->assertSame('Online', AnalyticDimension::withoutGlobalScopes()->find($dimensionB->id)->name);
    }

    public function test_company_a_cannot_sync_dimensions_onto_company_bs_transaction(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);

        $dimensionA = $this->withActiveCompany($companyA, fn () => $this->service->create([
            'type' => AnalyticDimensionType::BRANCH->value,
            'code' => 'B-01',
            'name' => 'Tehran',
        ]));

        $documentB = $this->withActiveCompany($companyB, fn () => Document::factory()->create());
        $subjectB = $this->withActiveCompany($companyB, fn () => Subject::create(['company_id' => $companyB->id, 'name' => 'Subject B', 'code' => 'S1', 'parent_id' => null]));
        $transactionB = Transaction::create(['document_id' => $documentB->id, 'subject_id' => $subjectB->id, 'value' => 1000, 'desc' => 'x']);

        $this->asCompany($companyA, $actorA)
            ->put(route('analytic-dimensions.transactions.sync', $transactionB), ['dimension_ids' => [$dimensionA->id]])
            ->assertNotFound();

        $this->assertCount(0, $transactionB->fresh()->analyticDimensions);
    }

    public function test_company_a_cannot_tag_its_own_transaction_with_company_bs_dimension(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);
        $actorA = $this->actorInCompany($companyA);

        $dimensionB = $this->withActiveCompany($companyB, fn () => $this->service->create([
            'type' => AnalyticDimensionType::BRANCH->value,
            'code' => 'B-01',
            'name' => 'Tehran B',
        ]));

        $documentA = $this->withActiveCompany($companyA, fn () => Document::factory()->create());
        $subjectA = $this->withActiveCompany($companyA, fn () => Subject::create(['company_id' => $companyA->id, 'name' => 'Subject A', 'code' => 'S1', 'parent_id' => null]));
        $transactionA = Transaction::create(['document_id' => $documentA->id, 'subject_id' => $subjectA->id, 'value' => 1000, 'desc' => 'x']);

        $this->asCompany($companyA, $actorA)
            ->put(route('analytic-dimensions.transactions.sync', $transactionA), ['dimension_ids' => [$dimensionB->id]])
            ->assertSessionHasErrors('dimensions');

        $this->assertCount(0, $transactionA->fresh()->analyticDimensions);
    }
}
