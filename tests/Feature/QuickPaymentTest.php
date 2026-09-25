<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\User;
use App\Services\PaymentService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * Phase U: the guided "Collect money" / "Pay suppliers" page is a presentation layer over
 * the ageing report and the EXISTING invoices.payments.store route.
 */
class QuickPaymentTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 4400;

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
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['name' => 'Ali Trading', 'company_id' => $this->companyId]);

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

    private function cashSubjectId(): int
    {
        $cashBook = Subject::withoutGlobalScopes()->find((int) config('amir.cash_book'));

        return (int) Subject::factory()->withParent($cashBook)->create(['name' => 'صندوق', 'company_id' => $this->companyId])->id;
    }

    public function test_it_needs_both_the_page_permission_and_the_payment_permission(): void
    {
        $this->get(route('quick-payment.create', 'receive'))->assertForbidden();

        $this->grant('quick-payment.create');
        $this->get(route('quick-payment.create', 'receive'))->assertForbidden(); // no invoices.payments.store

        $this->grant('invoices.payments.store');
        $this->get(route('quick-payment.create', 'receive'))->assertOk();
    }

    public function test_an_unknown_direction_is_a_404(): void
    {
        $this->grant('quick-payment.create', 'invoices.payments.store');

        $this->get('/quick-payment/sideways')->assertNotFound();
    }

    public function test_receive_lists_customers_who_owe_and_pay_lists_suppliers_we_owe(): void
    {
        $this->grant('quick-payment.create', 'invoices.payments.store');
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 50, 100)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(3)->toDateString());
        $this->sell([$this->productItem($product, 1, 700)], true, ++$this->nextInvoiceNumber, Carbon::today()->toDateString());

        $this->get(route('quick-payment.create', 'receive'))->assertOk()->assertSee('Ali Trading')->assertSee('700', false);
        $this->get(route('quick-payment.create', 'pay'))->assertOk()->assertSee('Ali Trading');
    }

    public function test_an_empty_state_is_shown_when_nobody_owes_anything(): void
    {
        $this->grant('quick-payment.create', 'invoices.payments.store');

        $this->get(route('quick-payment.create', 'receive'))
            ->assertOk()
            ->assertSee(__('No customer owes you anything right now.'));
    }

    public function test_submitting_to_the_existing_payment_route_settles_the_invoice_and_clears_the_list(): void
    {
        $this->grant('quick-payment.create', 'invoices.payments.store');
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 50, 100)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(3)->toDateString());
        $sell = $this->sell([$this->productItem($product, 1, 700)], true, ++$this->nextInvoiceNumber, Carbon::today()->toDateString())['invoice'];

        $this->post(route('invoices.payments.store', $sell), [
            'amount' => '700',
            'subject_id' => $this->cashSubjectId(),
            'date' => convertToJalali(now()->toDateString(), true),
        ])->assertSessionHasNoErrors();

        $this->assertSame(0.0, app(PaymentService::class)->remainingAmount($sell->fresh()));
        $this->get(route('quick-payment.create', 'receive'))
            ->assertOk()
            ->assertDontSee('Ali Trading')
            ->assertSee(__('No customer owes you anything right now.'));
    }

    private function twoOpenSells(): array
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 50, 100)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(9)->toDateString());
        $old = $this->sell([$this->productItem($product, 1, 700)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(5)->toDateString())['invoice'];
        $new = $this->sell([$this->productItem($product, 1, 300)], true, ++$this->nextInvoiceNumber, Carbon::today()->toDateString())['invoice'];

        return [$old, $new];
    }

    private function storePayload(array $override = []): array
    {
        return array_replace([
            'party_id' => $this->customer->id,
            'amount' => '900',
            'subject_id' => $this->cashSubjectId(),
            'date' => convertToJalali(now()->toDateString(), true),
        ], $override);
    }

    public function test_one_amount_is_spread_over_the_oldest_invoices_first(): void
    {
        $this->grant('quick-payment.create', 'quick-payment.store', 'invoices.payments.store');
        [$old, $new] = $this->twoOpenSells();
        $payments = app(PaymentService::class);

        $this->post(route('quick-payment.store', 'receive'), $this->storePayload())->assertSessionHasNoErrors();

        $this->assertSame(0.0, $payments->remainingAmount($old->fresh()));
        $this->assertSame(100.0, $payments->remainingAmount($new->fresh()));
    }

    public function test_an_amount_above_the_open_balance_is_rejected_and_nothing_is_recorded(): void
    {
        $this->grant('quick-payment.create', 'quick-payment.store', 'invoices.payments.store');
        [$old, $new] = $this->twoOpenSells();
        $payments = app(PaymentService::class);

        $this->post(route('quick-payment.store', 'receive'), $this->storePayload(['amount' => '1001']))
            ->assertSessionHasErrors('amount');

        $this->assertSame(700.0, $payments->remainingAmount($old->fresh()));
        $this->assertSame(300.0, $payments->remainingAmount($new->fresh()));
    }

    public function test_a_party_with_nothing_open_is_a_404_and_the_payment_permission_is_required(): void
    {
        $this->grant('quick-payment.create', 'quick-payment.store');
        $this->twoOpenSells();
        $this->post(route('quick-payment.store', 'receive'), $this->storePayload())->assertForbidden();

        $this->grant('invoices.payments.store');
        $this->post(route('quick-payment.store', 'receive'), $this->storePayload(['party_id' => 999999]))->assertNotFound();
    }

    public function test_another_companys_open_invoices_never_appear(): void
    {
        $this->grant('quick-payment.create', 'invoices.payments.store');
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 50, 100)], true, ++$this->nextInvoiceNumber, Carbon::today()->subDays(3)->toDateString());
        $this->sell([$this->productItem($product, 1, 700)], true, ++$this->nextInvoiceNumber, Carbon::today()->toDateString());
        $this->get(route('quick-payment.create', 'receive'))->assertSee('Ali Trading'); // positive control

        // A member of a second company (grants are team-scoped: switch first, then grant -- guardrail #55).
        $other = Company::factory()->create();
        config(['active-company-id' => $other->id]);
        $otherUser = User::factory()->create();
        $other->users()->syncWithoutDetaching($otherUser);
        foreach (['quick-payment.create', 'invoices.payments.store'] as $permission) {
            $otherUser->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }

        $this->actingAs($otherUser)
            ->withCookies(['active-company-id' => (string) $other->id])
            ->get(route('quick-payment.create', 'receive'))
            ->assertOk()
            ->assertDontSee('Ali Trading');
    }
}
