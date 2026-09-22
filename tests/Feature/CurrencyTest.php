<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Currency;
use App\Models\User;
use App\Services\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * P3.4 (Multi-Currency Foundation), Epic Pack 3. Deliberately tests the
 * conversion/FX-difference math and reference data this foundation
 * provides, not a full multi-currency Invoice/Payment rewrite (out of
 * scope for a "foundation" phase -- see CurrencyService's own docblock).
 */
class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected CurrencyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        config(['active-company-id' => $this->company->id]);
        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);
        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->company->id]);
        $this->service = app(CurrencyService::class);
    }

    public function test_only_one_currency_can_be_the_base_currency_at_a_time(): void
    {
        $rial = $this->service->createCurrency(['code' => 'IRR', 'name' => 'Rial', 'is_base' => true]);
        $this->assertTrue($rial->fresh()->is_base);

        $usd = $this->service->createCurrency(['code' => 'USD', 'name' => 'US Dollar', 'is_base' => true]);

        $this->assertTrue($usd->fresh()->is_base);
        $this->assertFalse($rial->fresh()->is_base);
    }

    public function test_the_base_currencys_rate_is_always_1_regardless_of_recorded_rates(): void
    {
        $base = $this->service->createCurrency(['code' => 'IRR', 'name' => 'Rial', 'is_base' => true]);

        $this->assertSame(1.0, $this->service->rateOn($base, now()->toDateString()));
    }

    public function test_recording_a_rate_and_converting_a_foreign_amount_to_base(): void
    {
        $usd = $this->service->createCurrency(['code' => 'USD', 'name' => 'US Dollar']);
        $this->service->recordRate($usd, 580000, '2026-09-01', $this->user->id);

        $converted = $this->service->convertToBase(100, $usd, '2026-09-01');

        $this->assertEqualsWithDelta(58000000.0, $converted, 0.01);
    }

    public function test_a_transaction_uses_the_most_recent_rate_on_or_before_its_date_not_an_exact_match(): void
    {
        $usd = $this->service->createCurrency(['code' => 'USD', 'name' => 'US Dollar']);
        $this->service->recordRate($usd, 580000, '2026-09-01', $this->user->id);
        $this->service->recordRate($usd, 600000, '2026-09-10', $this->user->id);

        // No rate recorded for 2026-09-05 -- must fall back to the most
        // recent rate BEFORE it (09-01), never the later 09-10 rate.
        $rate = $this->service->rateOn($usd, '2026-09-05');

        $this->assertEqualsWithDelta(580000.0, $rate, 0.01);
    }

    public function test_converting_with_no_recorded_rate_at_all_is_rejected(): void
    {
        $usd = $this->service->createCurrency(['code' => 'USD', 'name' => 'US Dollar']);

        $this->expectException(ValidationException::class);
        $this->service->rateOn($usd, now()->toDateString());
    }

    public function test_re_recording_the_same_effective_date_corrects_the_rate_rather_than_duplicating_it(): void
    {
        $usd = $this->service->createCurrency(['code' => 'USD', 'name' => 'US Dollar']);
        $this->service->recordRate($usd, 580000, '2026-09-01', $this->user->id);
        $this->service->recordRate($usd, 590000, '2026-09-01', $this->user->id);

        $this->assertSame(1, $usd->exchangeRates()->count());
        $this->assertEqualsWithDelta(590000.0, $this->service->rateOn($usd, '2026-09-01'), 0.01);
    }

    public function test_fx_gain_when_the_settlement_rate_is_higher_than_the_original_rate(): void
    {
        $result = $this->service->fxDifference(100, 580000, 600000);

        $this->assertEqualsWithDelta(58000000.0, $result['original_base_amount'], 0.01);
        $this->assertEqualsWithDelta(60000000.0, $result['settlement_base_amount'], 0.01);
        $this->assertEqualsWithDelta(2000000.0, $result['difference'], 0.01);
    }

    public function test_fx_loss_when_the_settlement_rate_is_lower_than_the_original_rate(): void
    {
        $result = $this->service->fxDifference(100, 600000, 580000);

        $this->assertEqualsWithDelta(-2000000.0, $result['difference'], 0.01);
    }

    public function test_zero_fx_difference_when_the_rate_is_unchanged(): void
    {
        $result = $this->service->fxDifference(100, 580000, 580000);

        $this->assertSame(0.0, $result['difference']);
    }

    // --- Cross-company isolation -------------------------------------

    public function test_company_a_cannot_record_a_rate_for_company_bs_currency(): void
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
        $currencyB = $this->service->createCurrency(['code' => 'USD', 'name' => 'US Dollar']);
        config(['active-company-id' => $previous]);

        $this->actingAs($actorA)
            ->withCookie('active-company-id', (string) $companyA->id)
            ->post(route('currencies.rates.store', $currencyB), ['rate' => 999999, 'effective_date' => now()->toDateString()])
            ->assertNotFound();

        $this->assertSame(0, $currencyB->fresh()->exchangeRates()->count());
    }
}
