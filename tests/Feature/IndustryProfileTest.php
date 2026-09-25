<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\IndustrySettingHistory;
use App\Models\IndustrySettingValue;
use App\Models\User;
use App\Services\Industry\IndustryCalculatorService;
use App\Services\Industry\IndustryProfileRegistry;
use App\Services\Industry\IndustrySettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Industry Profiles: per-trade settings + calculators driven by JSON
 * definitions (resources/industry-profiles). Informational only -- no ledger,
 * stock or Trade behaviour (D-099 §12).
 */
class IndustryProfileTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = $company->id;
        config(['active-company-id' => $this->companyId]);

        $this->user = User::factory()->create();
        $company->users()->syncWithoutDetaching($this->user);
        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => (string) $this->companyId]);
    }

    private function grant(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
    }

    private function settings(): IndustrySettingsService
    {
        return app(IndustrySettingsService::class);
    }

    private function calc(string $profile, string $calculator, array $inputs): array
    {
        return app(IndustryCalculatorService::class)->run($profile, $calculator, $inputs);
    }

    // ---------------------------------------------------------------- registry

    public function test_every_shipped_definition_loads_and_is_valid(): void
    {
        $profiles = app(IndustryProfileRegistry::class)->all();

        foreach (['gold', 'iron', 'general', 'building_materials', 'mobile', 'auto_parts', 'apparel', 'food'] as $key) {
            $this->assertArrayHasKey($key, $profiles);
            $this->assertNotEmpty(app(IndustryProfileRegistry::class)->fields($key), "{$key} has no settings");
        }
    }

    public function test_every_calculator_setting_reference_points_to_a_real_setting(): void
    {
        $registry = app(IndustryProfileRegistry::class);

        foreach ($registry->all() as $key => $profile) {
            $fields = $registry->fields($key);

            foreach ($profile['calculators'] ?? [] as $calculator) {
                $refs = [];
                array_walk_recursive($calculator, function ($value, $name) use (&$refs) {
                    if (is_string($name) && str_ends_with($name, '_setting')) {
                        $refs[] = $value;
                    }
                });
                foreach ($calculator['components'] ?? [] as $component) {
                    foreach (['setting'] as $name) {
                        if (isset($component[$name])) {
                            $refs[] = $component[$name];
                        }
                    }
                    if (isset($component['of_by_setting']['setting'])) {
                        $refs[] = $component['of_by_setting']['setting'];
                    }
                    foreach ($component['subtract'] ?? [] as $setting) {
                        $refs[] = $setting;
                    }
                }
                foreach (($calculator['params']['units'] ?? []) as $unit) {
                    if (is_array($unit)) {
                        $refs[] = $unit['setting'];
                    }
                }
                foreach (array_merge($calculator['params']['factor']['multiply'] ?? [], $calculator['params']['factor']['divide'] ?? []) as $setting) {
                    $refs[] = $setting;
                }

                foreach ($refs as $ref) {
                    $this->assertArrayHasKey($ref, $fields, "{$key}/{$calculator['key']} references unknown setting {$ref}");
                }
            }
        }
    }

    public function test_an_invalid_definition_is_rejected(): void
    {
        $dir = sys_get_temp_dir().'/industry-'.uniqid();
        File::makeDirectory($dir);
        File::put($dir.'/bad.json', json_encode([
            'key' => 'bad', 'title' => 'Bad',
            'sections' => [['key' => 's', 'fields' => [['key' => 'x', 'type' => 'integer', 'title' => 'X', 'status' => 'made_up']]]],
        ]));

        try {
            $this->expectException(\InvalidArgumentException::class);
            (new IndustryProfileRegistry($dir))->all();
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_a_new_trade_needs_only_a_definition_file_and_no_code(): void
    {
        $dir = sys_get_temp_dir().'/industry-'.uniqid();
        File::makeDirectory($dir);
        File::put($dir.'/bakery.json', json_encode([
            'key' => 'bakery', 'title' => ['fa' => 'نانوایی', 'en' => 'Bakery'],
            'sections' => [['key' => 's', 'title' => 'S', 'fields' => [
                ['key' => 'loaf_grams', 'type' => 'integer', 'default' => 350, 'min' => 50, 'title' => 'Loaf', 'status' => 'assumed'],
            ]]],
        ]));

        try {
            $service = new IndustrySettingsService(new IndustryProfileRegistry($dir));

            $this->assertSame(350, $service->value('bakery', 'loaf_grams'));
            $service->update('bakery', ['loaf_grams' => '420'], $this->user->id);
            $this->assertSame(420, $service->value('bakery', 'loaf_grams'));
        } finally {
            File::deleteDirectory($dir);
        }
    }

    // ---------------------------------------------------------------- settings

    public function test_value_falls_back_to_the_definition_default_then_the_override(): void
    {
        $this->assertSame(750, $this->settings()->value('gold', 'standard_purity'));

        $this->settings()->update('gold', ['standard_purity' => '740'], $this->user->id);
        $this->assertSame(740, $this->settings()->value('gold', 'standard_purity'));
    }

    public function test_update_records_history_and_reset_restores_the_default(): void
    {
        $this->settings()->update('gold', ['retail_profit_percent' => '8.5'], $this->user->id);

        $this->assertSame(8.5, $this->settings()->value('gold', 'retail_profit_percent'));
        $this->assertDatabaseHas('industry_setting_histories', [
            'company_id' => $this->companyId, 'profile_key' => 'gold', 'setting_key' => 'retail_profit_percent',
            'action' => 'update', 'old_value' => null, 'new_value' => '8.5', 'changed_by' => $this->user->id,
        ]);

        $this->settings()->update('gold', ['retail_profit_percent' => ''], $this->user->id);

        $this->assertSame(7.0, $this->settings()->value('gold', 'retail_profit_percent'));
        $this->assertDatabaseHas('industry_setting_histories', ['setting_key' => 'retail_profit_percent', 'action' => 'reset', 'old_value' => '8.5']);
        $this->assertSame(0, IndustrySettingValue::where('setting_key', 'retail_profit_percent')->count());
    }

    public function test_unchanged_values_write_no_history(): void
    {
        $this->settings()->update('gold', ['standard_purity' => '740'], $this->user->id);
        $changed = $this->settings()->update('gold', ['standard_purity' => '740'], $this->user->id);

        $this->assertSame(0, $changed);
        $this->assertSame(1, IndustrySettingHistory::where('setting_key', 'standard_purity')->count());
    }

    public function test_validation_rejects_unknown_keys_out_of_range_and_bad_types_atomically(): void
    {
        try {
            $this->settings()->update('gold', [
                'standard_purity' => '750',
                'retail_profit_percent' => '150',
                'rounding_mode' => 'sideways',
                'weight_decimals' => '2.5',
                'nonsense' => '1',
            ], $this->user->id);
            $this->fail('Expected validation failure');
        } catch (ValidationException $e) {
            $this->assertEqualsCanonicalizing(
                ['settings.retail_profit_percent', 'settings.rounding_mode', 'settings.weight_decimals', 'settings.nonsense'],
                array_keys($e->errors())
            );
        }

        // Nothing was written, not even the valid one.
        $this->assertSame(0, IndustrySettingValue::count());
    }

    public function test_confirm_marks_a_setting_and_editing_clears_the_confirmation(): void
    {
        $this->settings()->confirm('gold', 'soot_definition', $this->user->id);

        $effective = $this->settings()->effective('gold');
        $this->assertNotNull($effective['soot_definition']['confirmed_at']);
        $this->assertFalse($effective['soot_definition']['is_overridden']);

        $this->settings()->update('gold', ['soot_definition' => 'purity_point'], $this->user->id);
        $this->assertNull($this->settings()->effective('gold')['soot_definition']['confirmed_at']);
    }

    public function test_pending_counts_drop_when_a_setting_is_confirmed(): void
    {
        $before = $this->settings()->pendingCounts('gold');
        $this->assertGreaterThan(0, $before['needs_confirmation']);

        $this->settings()->confirm('gold', 'soot_definition', $this->user->id);

        $after = $this->settings()->pendingCounts('gold');
        $this->assertSame($before['needs_confirmation'] - 1, $after['needs_confirmation']);
    }

    public function test_settings_are_isolated_between_companies(): void
    {
        $this->settings()->update('gold', ['standard_purity' => '740'], $this->user->id);
        $this->assertSame(740, $this->settings()->value('gold', 'standard_purity'));

        $other = Company::factory()->create();
        config(['active-company-id' => $other->id]);

        $this->assertSame(750, $this->settings()->value('gold', 'standard_purity'));
        $this->assertSame(0, IndustrySettingHistory::count());

        // Positive control: the first company still has its own override.
        config(['active-company-id' => $this->companyId]);
        $this->assertSame(740, $this->settings()->value('gold', 'standard_purity'));
    }

    public function test_activation_toggles_per_company(): void
    {
        $this->assertFalse($this->settings()->isActive('gold'));

        $this->settings()->setActive('gold', true, $this->user->id);
        $this->assertTrue($this->settings()->isActive('gold'));

        $other = Company::factory()->create();
        config(['active-company-id' => $other->id]);
        $this->assertFalse($this->settings()->isActive('gold'));

        config(['active-company-id' => $this->companyId]);
        $this->settings()->setActive('gold', false, $this->user->id);
        $this->assertFalse($this->settings()->isActive('gold'));
    }

    // -------------------------------------------------------------- calculators

    public function test_gold_purity_normalization_uses_the_reference_purity(): void
    {
        $result = $this->calc('gold', 'purity_normalize', ['weight' => 100, 'purity' => 747]);

        $this->assertEqualsWithDelta(99.6, $result['total'], 0.0001);
    }

    public function test_purity_normalization_follows_company_settings_without_code_changes(): void
    {
        $this->settings()->update('gold', ['standard_purity' => '800', 'weight_decimals' => '1'], $this->user->id);

        $result = $this->calc('gold', 'purity_normalize', ['weight' => 100, 'purity' => 750]);

        $this->assertEqualsWithDelta(93.8, $result['total'], 0.0001); // 93.75 -> 93.8 half-up at 1 dp
    }

    public function test_gold_retail_price_layers_wage_profit_and_vat_correctly(): void
    {
        // 10 g @ 750, 5,000,000 / g => raw 50,000,000; wage 10% = 5,000,000;
        // profit 7% of (raw+wage) = 3,850,000; VAT 10% of (wage+profit) = 885,000.
        $result = $this->calc('gold', 'retail_price', ['weight' => 10, 'purity' => 750, 'price_per_gram' => 5000000]);

        $values = collect($result['lines'])->pluck('value')->all();
        $this->assertSame([50000000.0, 5000000.0, 3850000.0, 885000.0, 59735000.0], array_map('floatval', $values));
    }

    public function test_gold_retail_price_respects_profit_base_and_vat_scope_settings(): void
    {
        $this->settings()->update('gold', ['profit_base' => 'raw_only', 'vat_on_wage_and_profit_only' => '0'], $this->user->id);

        // profit 7% of raw = 3,500,000; VAT 10% of (raw+wage+profit)= 5,850,000.
        $result = $this->calc('gold', 'retail_price', ['weight' => 10, 'purity' => 750, 'price_per_gram' => 5000000, 'wage_percent' => 10]);

        $values = collect($result['lines'])->pluck('value')->map(fn ($v) => (float) $v)->all();
        $this->assertSame([50000000.0, 5000000.0, 3500000.0, 5850000.0, 64350000.0], $values);
    }

    public function test_trade_in_estimate_applies_the_purity_haircut(): void
    {
        // 10 g @ 750, haircut 10 => 740: 10 * 740 / 750 * 5,000,000 = 49,333,333.
        $result = $this->calc('gold', 'tradein_value', ['weight' => 10, 'purity' => 750, 'price_per_gram' => 5000000]);

        $this->assertSame(49333333.0, (float) $result['total']);

        $this->settings()->update('gold', ['tradein_purity_haircut_points' => '0'], $this->user->id);
        $this->assertSame(50000000.0, (float) $this->calc('gold', 'tradein_value', ['weight' => 10, 'purity' => 750, 'price_per_gram' => 5000000])['total']);
    }

    public function test_mazaneh_converts_to_a_reference_gram_price(): void
    {
        // divisor = 4.6083 * 705 / 750 = 4.331802
        $result = $this->calc('gold', 'mazaneh_to_gram', ['value' => 22500000]);

        $this->assertEqualsWithDelta(22500000 / 4.331802, $result['total'], 1.0);
    }

    public function test_iron_calculators(): void
    {
        $convert = $this->calc('iron', 'unit_convert', ['value' => 2.5, 'from' => 'ton', 'to' => 'kg']);
        $this->assertSame(2500.0, (float) $convert['total']);

        // Rebar 12 mm x 12 m: pi * 0.006^2 * 12 * 7850 = 10.654 kg per bar.
        $rebar = $this->calc('iron', 'rebar_weight', ['diameter_mm' => 12, 'length_m' => 12, 'count' => 100]);
        $this->assertEqualsWithDelta(1065.4, $rebar['total'], 0.5);

        // 2 t @ 30,000,000: margin 3% = 1,800,000; VAT 10% of (base+margin) = 6,180,000... check layers.
        $price = $this->calc('iron', 'sale_price', ['weight' => 2, 'price_per_ton' => 30000000]);
        $values = collect($price['lines'])->pluck('value')->map(fn ($v) => (float) $v)->all();
        $this->assertSame([60000000.0, 1800000.0, 6180000.0, 67980000.0], $values);
    }

    public function test_building_material_units_use_the_bag_weight_setting(): void
    {
        $this->assertSame(1.0, (float) $this->calc('building_materials', 'unit_convert', ['value' => 20, 'from' => 'bag', 'to' => 'ton'])['total']);

        $this->settings()->update('building_materials', ['bag_weight_kg' => '25'], $this->user->id);
        $this->assertSame(0.5, (float) $this->calc('building_materials', 'unit_convert', ['value' => 20, 'from' => 'bag', 'to' => 'ton'])['total']);
    }

    public function test_calculator_input_validation(): void
    {
        $this->expectException(ValidationException::class);
        $this->calc('gold', 'purity_normalize', ['weight' => 'abc', 'purity' => 750]);
    }

    // --------------------------------------------------------------- controller

    public function test_pages_require_permission(): void
    {
        $this->get(route('industry-profiles.index'))->assertForbidden();
        $this->get(route('industry-profiles.show', 'gold'))->assertForbidden();
    }

    public function test_index_and_show_render_for_permitted_users(): void
    {
        $this->grant('industry-profiles.index', 'industry-profiles.show');

        $this->get(route('industry-profiles.index'))->assertOk()->assertSee('gold', false);
        $this->get(route('industry-profiles.show', 'gold'))->assertOk()->assertSee('750', false);
        $this->get(route('industry-profiles.show', 'iron'))->assertOk();
    }

    public function test_unknown_profile_is_a_404(): void
    {
        $this->grant('industry-profiles.show');

        $this->get(route('industry-profiles.show', 'nonexistent'))->assertNotFound();
    }

    public function test_update_via_http_persists_and_shows_on_the_dashboard(): void
    {
        $this->grant('industry-profiles.show', 'industry-profiles.update');

        $this->put(route('industry-profiles.update', 'gold'), ['settings' => ['retail_profit_percent' => '9']])
            ->assertRedirect(route('industry-profiles.show', 'gold'));

        $this->assertSame(9.0, $this->settings()->value('gold', 'retail_profit_percent'));
    }

    public function test_update_via_http_rejects_invalid_values(): void
    {
        $this->grant('industry-profiles.show', 'industry-profiles.update');

        $this->from(route('industry-profiles.show', 'gold'))
            ->put(route('industry-profiles.update', 'gold'), ['settings' => ['retail_profit_percent' => '999']])
            ->assertSessionHasErrors('settings.retail_profit_percent');

        $this->assertSame(0, IndustrySettingValue::count());
    }

    public function test_update_requires_the_update_permission(): void
    {
        $this->grant('industry-profiles.show');

        $this->put(route('industry-profiles.update', 'gold'), ['settings' => ['retail_profit_percent' => '9']])->assertForbidden();
    }

    public function test_confirm_and_toggle_via_http(): void
    {
        $this->grant('industry-profiles.show', 'industry-profiles.confirm', 'industry-profiles.toggle');

        $this->post(route('industry-profiles.confirm', ['gold', 'soot_definition']))->assertRedirect();
        $this->assertNotNull($this->settings()->effective('gold')['soot_definition']['confirmed_at']);

        $this->post(route('industry-profiles.toggle', 'gold'))->assertRedirect();
        $this->assertTrue($this->settings()->isActive('gold'));
    }

    public function test_calculate_endpoint_returns_json_and_422_on_bad_input(): void
    {
        $this->grant('industry-profiles.calculate');

        $this->postJson(route('industry-profiles.calculate', ['gold', 'purity_normalize']), ['inputs' => ['weight' => 100, 'purity' => 747]])
            ->assertOk()
            ->assertJsonPath('total', 99.6);

        $this->postJson(route('industry-profiles.calculate', ['gold', 'purity_normalize']), ['inputs' => ['weight' => 'x', 'purity' => 747]])
            ->assertStatus(422);

        $this->postJson(route('industry-profiles.calculate', ['gold', 'no_such_calc']), ['inputs' => []])
            ->assertStatus(422);
    }

    public function test_another_companys_overrides_never_appear_on_the_dashboard(): void
    {
        $other = Company::factory()->create();
        config(['active-company-id' => $other->id]);
        IndustrySettingValue::create(['profile_key' => 'gold', 'setting_key' => 'standard_purity', 'value' => '999']);
        config(['active-company-id' => $this->companyId]);

        $this->grant('industry-profiles.show');

        $this->get(route('industry-profiles.show', 'gold'))->assertOk()->assertDontSee('999', false);
        $this->assertSame(750, $this->settings()->value('gold', 'standard_purity'));
    }
}
