<?php

namespace Tests\Feature;

use App\Models\AncillaryCost;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * Verifies whether AncillaryCostTransactionBuilder's document balances when a non-zero VAT
 * percentage is entered through the REAL create form/request -- not by constructing the
 * builder's $data array by hand, which risks assuming a shape the app never actually produces.
 */
class AncillaryCostVatBalanceTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 8800;

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
        foreach (['invoices.ancillary-costs.create', 'invoices.ancillary-costs.store', 'ancillary-costs.approve'] as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
        $this->actingAs($this->user);
    }

    public function test_an_ancillary_cost_with_vat_submitted_through_the_real_form_approves_with_a_balanced_document(): void
    {
        $product = Product::withoutGlobalScopes()->find(
            $this->createProduct()->id
        );
        $invoice = $this->buy([$this->productItem($product, 5, 100)], true, ++$this->nextInvoiceNumber)['invoice'];

        $response = $this->post(route('invoices.ancillary-costs.store', $invoice), [
            'customer_id' => $this->customer->id,
            'invoice_id' => $invoice->id,
            'type' => 'Shipping',
            'vat' => 10,
            'date' => convertToJalali($invoice->date, true),
            'ancillaryCosts' => [
                ['product_id' => $product->id, 'amount' => 1000],
            ],
            'approve' => 1,
        ]);

        $response->assertSessionDoesntHaveErrors();

        $ancillaryCost = AncillaryCost::firstOrFail();
        $this->assertSame(1100.0, (float) $ancillaryCost->amount, 'amount must be the VAT-inclusive grand total (1000 + 10%)');
        $this->assertSame(100.0, (float) $ancillaryCost->vat, 'vat must be the VAT amount (10% of 1000)');
        $this->assertNotNull($ancillaryCost->document_id, 'approval must have succeeded, producing a document');
        $this->assertEqualsWithDelta(0.0, (float) $ancillaryCost->document->transactions()->sum('value'), 0.01, 'the posted document must balance to zero');
    }
}
