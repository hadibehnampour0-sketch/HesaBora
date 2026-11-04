<?php

namespace Tests\Feature;

use App\Enums\FixedAssetStatus;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\Subject;
use App\Models\User;
use App\Services\FixedAssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * P3.5 (Fixed Assets), Epic Pack 3. Straight-line depreciation, balanced
 * document posting, the depreciable-amount cap guard, and the
 * duplicate-period-posting guard.
 */
class FixedAssetTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected FixedAssetService $service;

    protected int $assetSubjectId;

    protected int $expenseSubjectId;

    protected int $accumulatedSubjectId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        config(['active-company-id' => $this->company->id]);
        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);
        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->company->id]);
        $this->service = app(FixedAssetService::class);

        $this->assetSubjectId = Subject::create(['company_id' => $this->company->id, 'name' => 'Equipment', 'code' => 'A1', 'parent_id' => null])->id;
        $this->expenseSubjectId = Subject::create(['company_id' => $this->company->id, 'name' => 'Depreciation Expense', 'code' => 'E1', 'parent_id' => null])->id;
        $this->accumulatedSubjectId = Subject::create(['company_id' => $this->company->id, 'name' => 'Accumulated Depreciation', 'code' => 'A2', 'parent_id' => null])->id;
    }

    private function registerAsset(float $cost = 12000, float $salvage = 0, int $months = 12): FixedAsset
    {
        return $this->service->register([
            'code' => 'FA-01',
            'name' => 'Delivery Van',
            'acquisition_date' => now()->subYear()->toDateString(),
            'acquisition_cost' => $cost,
            'salvage_value' => $salvage,
            'useful_life_months' => $months,
            'asset_subject_id' => $this->assetSubjectId,
            'depreciation_expense_subject_id' => $this->expenseSubjectId,
            'accumulated_depreciation_subject_id' => $this->accumulatedSubjectId,
        ], $this->user);
    }

    public function test_salvage_value_must_be_less_than_acquisition_cost(): void
    {
        $this->expectException(ValidationException::class);

        $this->registerAsset(cost: 1000, salvage: 1000);
    }

    public function test_monthly_depreciation_is_straight_line(): void
    {
        $asset = $this->registerAsset(cost: 12000, salvage: 0, months: 12);

        $this->assertEqualsWithDelta(1000.0, $asset->monthlyDepreciation(), 0.01);
    }

    public function test_posting_depreciation_creates_exactly_one_balanced_document(): void
    {
        $asset = $this->registerAsset(cost: 12000, salvage: 0, months: 12);

        $entry = $this->service->postDepreciation($asset, now()->toDateString(), $this->user);

        $this->assertEqualsWithDelta(1000.0, (float) $entry->amount, 0.01);
        $this->assertNotNull($entry->document);
        $this->assertEqualsWithDelta(0, $entry->document->transactions()->sum('value'), 0.01);
        $this->assertEqualsWithDelta(1000.0, $this->service->accumulatedDepreciation($asset), 0.01);
    }

    public function test_posting_the_same_period_twice_is_rejected(): void
    {
        $asset = $this->registerAsset();
        $period = now()->toDateString();
        $this->service->postDepreciation($asset, $period, $this->user);

        $this->expectException(ValidationException::class);

        try {
            $this->service->postDepreciation($asset, $period, $this->user);
        } finally {
            $this->assertSame(1, $asset->depreciationEntries()->count());
        }
    }

    public function test_depreciation_never_exceeds_the_depreciable_amount_even_across_many_periods(): void
    {
        $asset = $this->registerAsset(cost: 1200, salvage: 0, months: 12);

        for ($i = 0; $i < 12; $i++) {
            $this->service->postDepreciation($asset, now()->addMonths($i)->toDateString(), $this->user);
        }

        $this->assertEqualsWithDelta(1200.0, $this->service->accumulatedDepreciation($asset), 0.01);

        $this->expectException(ValidationException::class);
        $this->service->postDepreciation($asset, now()->addMonths(12)->toDateString(), $this->user);
    }

    public function test_a_disposed_asset_can_no_longer_be_depreciated(): void
    {
        $asset = $this->registerAsset();
        $this->service->dispose($asset, now()->toDateString());

        $this->expectException(ValidationException::class);
        $this->service->postDepreciation($asset->fresh(), now()->addMonth()->toDateString(), $this->user);
    }

    public function test_disposing_an_asset_updates_its_status(): void
    {
        $asset = $this->registerAsset();

        $disposed = $this->service->dispose($asset, now()->toDateString());

        $this->assertSame(FixedAssetStatus::DISPOSED, $disposed->status);
    }

    // --- Cross-company isolation -------------------------------------

    public function test_company_a_cannot_post_depreciation_for_company_bs_asset(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);

        $role = Role::firstOrCreate(['name' => 'Super-Admin']);
        $actorA = User::factory()->create();
        $actorA->companies()->syncWithoutDetaching([$companyA->id]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(0);
        $actorA->assignRole($role);
        $registrar->setPermissionsTeamId(null);

        $previous = config('active-company-id');
        config(['active-company-id' => $companyB->id]);
        $subjectAsset = Subject::create(['company_id' => $companyB->id, 'name' => 'A', 'code' => 'B1', 'parent_id' => null])->id;
        $subjectExpense = Subject::create(['company_id' => $companyB->id, 'name' => 'E', 'code' => 'B2', 'parent_id' => null])->id;
        $subjectAccum = Subject::create(['company_id' => $companyB->id, 'name' => 'AD', 'code' => 'B3', 'parent_id' => null])->id;
        $assetB = $this->service->register([
            'code' => 'FA-B', 'name' => 'B Asset', 'acquisition_date' => now()->toDateString(),
            'acquisition_cost' => 1000, 'useful_life_months' => 10,
            'asset_subject_id' => $subjectAsset, 'depreciation_expense_subject_id' => $subjectExpense,
            'accumulated_depreciation_subject_id' => $subjectAccum,
        ], $this->user);
        config(['active-company-id' => $previous]);

        $this->actingAs($actorA)
            ->withCookie('active-company-id', (string) $companyA->id)
            ->post(route('fixed-assets.depreciation.store', $assetB), ['period_date' => now()->toDateString()])
            ->assertNotFound();

        $this->assertSame(0, $assetB->fresh()->depreciationEntries()->count());
    }

    // --- Equipment Maintenance Logbook & AI TCO Intelligence ---

    public function test_quick_register_equipment_with_auto_resolved_subjects_and_category(): void
    {
        $asset = $this->service->register([
            'name' => 'کولر گازی ۲۴۰۰۰ جنرال مغازه',
            'category' => 'facility',
            'serial_or_plate' => 'GN-99281',
            'location' => 'مغازه اصلی',
            'acquisition_date' => now()->subMonths(6)->toDateString(),
            'acquisition_cost' => 35000000,
        ], $this->user);

        $this->assertNotNull($asset->id);
        $this->assertStringStartsWith('AST-', $asset->code);
        $this->assertEquals('facility', $asset->category);
        $this->assertEquals('GN-99281', $asset->serial_or_plate);
        $this->assertEquals('مغازه اصلی', $asset->location);
        $this->assertNotNull($asset->asset_subject_id);
        $this->assertNotNull($asset->depreciation_expense_subject_id);
        $this->assertNotNull($asset->accumulated_depreciation_subject_id);
    }

    public function test_record_maintenance_posts_balanced_accounting_document_and_links_logbook(): void
    {
        $asset = $this->registerAsset(20000000);

        $maintenance = $this->service->recordMaintenance($asset, [
            'title' => 'تعویض روغن، لنت و فیلترها',
            'maintenance_type' => 'service',
            'date' => now()->toDateString(),
            'amount' => 2500000,
            'odometer_or_hours' => 85000,
            'description' => 'سرویس دوره ای در تعمیرگاه مجاز',
        ], $this->user);

        $this->assertNotNull($maintenance->id);
        $this->assertEquals(2500000, $maintenance->amount);
        $this->assertEquals(85000, $maintenance->odometer_or_hours);
        $this->assertNotNull($maintenance->document_id);

        // Check the accounting document is balanced (sum = 0)
        $doc = $maintenance->document;
        $this->assertNotNull($doc);
        $this->assertNotNull($doc->approved_at);
        $this->assertEquals(0, $doc->transactions()->sum('value'));
        $this->assertCount(2, $doc->transactions);

        // Verify logbook relation
        $this->assertCount(1, $asset->fresh()->maintenances);
        $this->assertEquals(2500000, $asset->fresh()->totalMaintenanceCost());
    }

    public function test_tco_metrics_and_ai_burn_recommendation(): void
    {
        // Asset purchased for 10,000,000
        $asset = $this->registerAsset(10000000);

        // Healthy state (0 maintenance)
        $this->assertEquals('healthy', $asset->healthStatus()['status']);
        $this->assertFalse($asset->aiRecommendation()['is_alert']);

        // Record first repair: 2,000,000 (20% of cost -> moderate)
        $this->service->recordMaintenance($asset, [
            'title' => 'تعمیر کمپرسور',
            'amount' => 2000000,
            'date' => now()->subMonth()->toDateString(),
        ], $this->user);

        $asset = $asset->fresh();
        $this->assertEquals(20.0, $asset->maintenanceToAssetRatio());
        $this->assertEquals('moderate', $asset->healthStatus()['status']);

        // Record second repair: 2,500,000 (Total 4,500,000 = 45% -> critical_burn)
        $this->service->recordMaintenance($asset, [
            'title' => 'تعمیر موتور و نشتی گاز',
            'amount' => 2500000,
            'date' => now()->toDateString(),
        ], $this->user);

        $asset = $asset->fresh();
        $this->assertEquals(45.0, $asset->maintenanceToAssetRatio());
        $this->assertEquals('critical', $asset->healthStatus()['status']);

        // AI recommendation flags critical alert and advises replacement
        $ai = $asset->aiRecommendation();
        $this->assertTrue($ai['is_alert']);
        $this->assertEquals('critical', $ai['level']);
        $this->assertStringContainsString('فرسایش', $ai['message']);
        $this->assertStringContainsString('اسقاط', $ai['message']);
        $this->assertStringContainsString('جایگزینی', $ai['action_label']);
    }

    public function test_maintenance_http_endpoints(): void
    {
        $role = Role::firstOrCreate(['name' => 'Super-Admin']);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(0);
        $this->user->assignRole($role);
        $registrar->setPermissionsTeamId(null);

        $asset = $this->registerAsset(50000000);

        // GET index
        $this->get(route('fixed-assets.index'))
            ->assertOk()
            ->assertSee('اموال، تجهیزات و لاگ‌بوک تعمیرات');

        // GET show
        $this->get(route('fixed-assets.show', $asset))
            ->assertOk()
            ->assertSee($asset->name)
            ->assertSee('لاگ‌بوک و تاریخچه تعمیرات');

        // POST store maintenance
        $response = $this->post(route('fixed-assets.maintenances.store', $asset), [
            'title' => 'تعمیر استارت و باطری',
            'maintenance_type' => 'repair',
            'date' => convertToJalali(now(), true),
            'amount' => 3200000,
            'description' => 'باطری اتمیک ۶۰ آمپر',
        ]);

        $response->assertRedirect(route('fixed-assets.show', $asset));
        $this->assertDatabaseHas('asset_maintenances', [
            'fixed_asset_id' => $asset->id,
            'title' => 'تعمیر استارت و باطری',
            'amount' => 3200000,
        ]);

        $maint = $asset->fresh()->maintenances->first();

        // DELETE destroy maintenance
        $delResponse = $this->delete(route('fixed-assets.maintenances.destroy', [$asset, $maint]));
        $delResponse->assertRedirect(route('fixed-assets.show', $asset));
        $this->assertDatabaseMissing('asset_maintenances', ['id' => $maint->id]);
    }
}
