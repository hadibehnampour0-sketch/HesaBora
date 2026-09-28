<?php

namespace Tests\Feature;

use App\Enums\AnalyticDimensionType;
use App\Models\AnalyticDimension;
use App\Models\Company;
use App\Models\Document;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Tagging a document's transaction lines with an analytic dimension, from the document page. */
class DocumentDimensionTaggingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Document $document;

    private Transaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        config(['active-company-id' => $company->id]);
        $this->user = User::factory()->create();
        $company->users()->syncWithoutDetaching($this->user);
        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $company->id]);

        $this->document = Document::factory()->create();
        $subject = Subject::create(['company_id' => $company->id, 'name' => 'Subject', 'code' => 'S1', 'parent_id' => null]);
        $this->transaction = Transaction::create(['document_id' => $this->document->id, 'subject_id' => $subject->id, 'value' => 1000, 'desc' => 'test']);
    }

    private function grant(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
    }

    public function test_the_tagging_control_is_hidden_without_the_sync_permission(): void
    {
        $this->grant('documents.show');
        AnalyticDimension::create(['type' => AnalyticDimensionType::BRANCH->value, 'code' => 'B-01', 'name' => 'Tehran', 'is_active' => true]);

        $this->get(route('documents.show', $this->document))->assertOk()->assertDontSee(__('Dimensions'));
    }

    public function test_a_dimension_can_be_tagged_and_shows_on_the_document(): void
    {
        $this->grant('documents.show', 'analytic-dimensions.transactions.sync');
        $branch = AnalyticDimension::create(['type' => AnalyticDimensionType::BRANCH->value, 'code' => 'B-01', 'name' => 'Tehran', 'is_active' => true]);

        $this->get(route('documents.show', $this->document))->assertOk()->assertSee(__('Dimensions'));

        $this->put(route('analytic-dimensions.transactions.sync', $this->transaction), ['dimension_ids' => [$branch->id]])
            ->assertSessionHasNoErrors();

        $this->get(route('documents.show', $this->document))->assertOk()->assertSee('B-01');
        $this->assertCount(1, $this->transaction->fresh()->analyticDimensions);
    }

    public function test_only_active_dimensions_are_offered(): void
    {
        $this->grant('documents.show', 'analytic-dimensions.transactions.sync');
        AnalyticDimension::create(['type' => AnalyticDimensionType::BRANCH->value, 'code' => 'B-01', 'name' => 'Tehran', 'is_active' => false]);

        $this->get(route('documents.show', $this->document))->assertOk()->assertDontSee('B-01');
    }
}
