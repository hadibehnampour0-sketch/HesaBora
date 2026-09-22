<?php

namespace Tests\Feature;

use App\Services\TaxRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * P9.2 (Tax Rules engine). Mirrors CurrencyServiceTest's own coverage of
 * `rateOn()` -- as-of-date lookup, most-recent-on-or-before wins,
 * category-specific overrides the general rate, and no rate configured is
 * a hard error, never a silently guessed default.
 */
class TaxRuleServiceTest extends TestCase
{
    use RefreshDatabase;

    private TaxRuleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TaxRuleService::class);
    }

    public function test_it_returns_the_historically_correct_general_rate(): void
    {
        $this->service->recordRule(null, 9.0, false, '2023-03-21', '2024-03-19', 'Rate for 1402');
        $this->service->recordRule(null, 10.0, false, '2024-03-20', null, 'Rate for 1403 onward');

        $this->assertSame(9.0, $this->service->vatRateOn(null, Carbon::parse('2023-10-15')));
        $this->assertSame(10.0, $this->service->vatRateOn(null, Carbon::parse('2024-05-10')));
    }

    public function test_a_category_specific_rule_overrides_the_general_rate(): void
    {
        $this->service->recordRule(null, 10.0, false, '2024-01-01', null, 'General');
        $this->service->recordRule('2710000000001', 0.0, true, '2024-01-01', null, 'Exempt medicine category');

        $this->assertSame(10.0, $this->service->vatRateOn('1111111111111', Carbon::parse('2024-06-01')));
        $this->assertSame(0.0, $this->service->vatRateOn('2710000000001', Carbon::parse('2024-06-01')));
        $this->assertTrue($this->service->isExemptOn('2710000000001', Carbon::parse('2024-06-01')));
    }

    public function test_a_category_specific_rule_falls_back_to_the_general_rate_before_its_own_effective_date(): void
    {
        $this->service->recordRule(null, 10.0, false, '2023-01-01', null, 'General');
        $this->service->recordRule('2710000000001', 0.0, true, '2024-06-01', null, 'Exemption starts mid-2024');

        // Before the category-specific exemption takes effect, the general
        // rate still applies to that category.
        $this->assertSame(10.0, $this->service->vatRateOn('2710000000001', Carbon::parse('2024-01-01')));
        $this->assertSame(0.0, $this->service->vatRateOn('2710000000001', Carbon::parse('2024-07-01')));
    }

    public function test_no_configured_rate_throws_instead_of_guessing(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->vatRateOn(null, Carbon::parse('2020-01-01'));
    }

    public function test_recording_a_rule_a_second_time_for_the_same_category_and_date_updates_the_existing_row(): void
    {
        $rule = $this->service->recordRule(null, 9.0, false, '2024-01-01', null, 'Initial');
        $this->service->recordRule(null, 9.5, false, '2024-01-01', null, 'Corrected', $rule);

        $this->assertSame(1, \App\Models\TaxRule::whereNull('sstid')->count());
        $this->assertSame(9.5, $this->service->vatRateOn(null, Carbon::parse('2024-06-01')));
    }
}
