<?php

namespace Tests\Feature;

use App\Enums\TaxDeductibleStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\AncillaryCostService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class AncillaryCostTaxDeductibleStatusTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 8400;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;
        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        ProductGroup::factory()->withSubjects()->create(['name' => 'g', 'vat' => 0, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'g', 'company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);

        $this->user = User::factory()->create();
        Company::find($this->companyId)->users()->syncWithoutDetaching($this->user);
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'ancillary-costs.set-tax-deductible-status']));
        $this->actingAs($this->user);
    }

    private function buyInvoiceForAncillaryCost(): Invoice
    {
        $product = $this->createProduct();

        return $this->findInvoice(
            $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber)['invoice']->id
        );
    }

    public function test_classifying_an_ancillary_cost_as_non_deductible(): void
    {
        $invoice = $this->buyInvoiceForAncillaryCost();
        $productId = (int) $invoice->items->first()->itemable_id;
        $result = AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'company_id' => $this->companyId,
            'date' => $invoice->date->toDateString(),
            'type' => 'Shipping',
            'amount' => 100,
            'vatPrice' => 0,
            'ancillaryCosts' => [['product_id' => $productId, 'amount' => 100]],
        ], false);
        $ancillaryCost = $result['ancillaryCost'];

        $response = $this->patch(route('ancillary-costs.set-tax-deductible-status', $ancillaryCost), [
            'tax_deductible_status' => TaxDeductibleStatus::NON_DEDUCTIBLE->value,
        ]);

        $response->assertRedirect();
        $this->assertSame(TaxDeductibleStatus::NON_DEDUCTIBLE, $ancillaryCost->refresh()->tax_deductible_status);
    }

    public function test_an_invalid_status_value_is_rejected(): void
    {
        $invoice = $this->buyInvoiceForAncillaryCost();
        $productId = (int) $invoice->items->first()->itemable_id;
        $result = AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'company_id' => $this->companyId,
            'date' => $invoice->date->toDateString(),
            'type' => 'Shipping',
            'amount' => 100,
            'vatPrice' => 0,
            'ancillaryCosts' => [['product_id' => $productId, 'amount' => 100]],
        ], false);
        $ancillaryCost = $result['ancillaryCost'];

        $response = $this->patch(route('ancillary-costs.set-tax-deductible-status', $ancillaryCost), [
            'tax_deductible_status' => 'not-a-real-status',
        ]);

        $response->assertSessionHasErrors('tax_deductible_status');
        $this->assertNull($ancillaryCost->refresh()->tax_deductible_status);
    }

    public function test_the_permission_is_required(): void
    {
        $invoice = $this->buyInvoiceForAncillaryCost();
        $productId = (int) $invoice->items->first()->itemable_id;
        $result = AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'company_id' => $this->companyId,
            'date' => $invoice->date->toDateString(),
            'type' => 'Shipping',
            'amount' => 100,
            'vatPrice' => 0,
            'ancillaryCosts' => [['product_id' => $productId, 'amount' => 100]],
        ], false);
        $ancillaryCost = $result['ancillaryCost'];

        $this->user->revokePermissionTo('ancillary-costs.set-tax-deductible-status');

        $response = $this->patch(route('ancillary-costs.set-tax-deductible-status', $ancillaryCost), [
            'tax_deductible_status' => TaxDeductibleStatus::DEDUCTIBLE->value,
        ]);

        $response->assertForbidden();
    }
}
