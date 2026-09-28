<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Manufacturing/BOM readiness only (Dakhl_MANUFACTURING_BOM_RESEARCH_AND_PLAN_2026-09-28.md,
 * Phase 0). Proves the new column is purely additive: existing products default to GENERAL and
 * nothing about product creation/behavior changes.
 */
class ProductTypeReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_existing_style_product_defaults_to_general_type(): void
    {
        $company = Company::factory()->create();
        config(['active-company-id' => $company->id]);
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $company->id]);

        $product = Product::factory()->withGroup($group)->withSubjects()->create(['company_id' => $company->id]);

        $this->assertSame(ProductType::GENERAL, $product->fresh()->type);
    }

    public function test_a_product_can_be_classified_for_a_future_bom(): void
    {
        $company = Company::factory()->create();
        config(['active-company-id' => $company->id]);
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $company->id]);

        $raw = Product::factory()->withGroup($group)->withSubjects()->create(['company_id' => $company->id, 'type' => ProductType::RAW_MATERIAL->value]);

        $this->assertSame(ProductType::RAW_MATERIAL, $raw->fresh()->type);
    }
}
