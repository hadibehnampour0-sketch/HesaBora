<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\PaymentTerm;
use App\Models\ProductGroup;
use App\Models\SettlementObligation;
use App\Models\Subject;
use App\Models\User;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/** Applying a payment term from the invoice page: previously the invoice screen had no way to reach `payment-terms.apply` at all. */
class InvoicePaymentTermTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 5500;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;
        config(['active-company-id' => $this->companyId]);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        ProductGroup::factory()->withSubjects()->create(['name' => 'g', 'vat' => 0, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'g', 'company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['name' => 'Sara Trading', 'company_id' => $this->companyId]);

        $this->user = User::factory()->create();
        Company::find($this->companyId)->users()->syncWithoutDetaching($this->user);
        $this->actingAs($this->user);
    }

    private function grant(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
    }

    public function test_a_payment_term_can_be_applied_from_the_invoice_page_and_then_the_picker_disappears(): void
    {
        $this->grant('invoices.show', 'payment-terms.apply', 'payment-terms.index', 'payment-terms.store', 'settlements.show');
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDay()->toDateString());
        $sell = $this->sell([$this->productItem($product, 1, 1000)], true, ++$this->nextInvoiceNumber, Carbon::today()->toDateString())['invoice'];

        $this->post(route('payment-terms.store'), [
            'code' => 'NET30', 'name' => 'Net 30',
            'lines' => [['days_offset' => 0, 'percentage' => 100]],
        ])->assertSessionHasNoErrors();
        $term = PaymentTerm::firstOrFail();

        $this->get(route('invoices.show', $sell))->assertOk()->assertSee(__('Apply a payment term...'));

        $this->post(route('payment-terms.apply', $sell), ['payment_term_id' => $term->id])->assertSessionHasNoErrors();

        $obligation = SettlementObligation::where('source_type', $sell->getMorphClass())->where('source_id', $sell->id)->firstOrFail();
        $this->assertEqualsWithDelta(1000.0, (float) $obligation->original_amount, 0.01);

        $this->get(route('invoices.show', $sell))
            ->assertOk()
            ->assertDontSee(__('Apply a payment term...'))
            ->assertSee(__('An instalment plan exists for this invoice.'))
            ->assertSee(route('settlements.show', $obligation), false);
    }

    public function test_the_picker_is_hidden_without_the_permission_or_when_nothing_is_owed(): void
    {
        $this->grant('invoices.show');
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 100)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDay()->toDateString());
        $sell = $this->sell([$this->productItem($product, 1, 1000)], true, ++$this->nextInvoiceNumber, Carbon::today()->toDateString())['invoice'];

        $this->get(route('invoices.show', $sell))->assertOk()->assertDontSee(__('Apply a payment term...'));

        $this->grant('payment-terms.apply', 'invoices.payments.store');
        $cashBook = Subject::withoutGlobalScopes()->find((int) config('amir.cash_book'));
        $cashSubject = Subject::factory()->withParent($cashBook)->create(['name' => 'صندوق', 'company_id' => $this->companyId]);
        $this->post(route('invoices.payments.store', $sell), [
            'amount' => '1000',
            'subject_id' => $cashSubject->id,
            'date' => convertToJalali(now()->toDateString(), true),
        ])->assertSessionHasNoErrors();

        // remainingAmount is now 0 -- the picker must not appear even though the permission is granted.
        $this->get(route('invoices.show', $sell))->assertOk()->assertDontSee(__('Apply a payment term...'));
    }
}
