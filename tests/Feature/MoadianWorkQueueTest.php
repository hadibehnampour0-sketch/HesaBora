<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\MoadianHistory;
use App\Models\ProductGroup;
use App\Models\User;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/** T-7: a work queue of approved sales invoices that have never successfully reached Moadian. */
class MoadianWorkQueueTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 8700;

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
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['name' => 'Never Sent Co', 'company_id' => $this->companyId]);

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

    public function test_the_queue_needs_its_own_permission(): void
    {
        $this->get(route('invoices.moadian-queue.index'))->assertForbidden();
    }

    public function test_never_sent_and_failed_invoices_are_listed_but_successful_ones_are_not(): void
    {
        $this->grant('invoices.moadian-queue.index');
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 30, 50)], true, ++$this->nextInvoiceNumber);

        $neverSent = $this->sell([$this->productItem($product, 1, 100)], true, ++$this->nextInvoiceNumber)['invoice'];

        $failed = $this->sell([$this->productItem($product, 1, 200)], true, ++$this->nextInvoiceNumber)['invoice'];
        MoadianHistory::create(['invoice_id' => $failed->id, 'data' => ['status' => 'FAILED', 'error' => 'bad signature']]);

        $succeeded = $this->sell([$this->productItem($product, 1, 300)], true, ++$this->nextInvoiceNumber)['invoice'];
        MoadianHistory::create(['invoice_id' => $succeeded->id, 'data' => ['status' => 'SUCCESS', 'referenceNumber' => 'REF-1']]);

        $response = $this->get(route('invoices.moadian-queue.index'))->assertOk();

        $response->assertSee(formatDocumentNumber($neverSent->number));
        $response->assertSee(formatDocumentNumber($failed->number))->assertSee('bad signature');
        $response->assertDontSee(formatDocumentNumber($succeeded->number));
    }

    public function test_retrying_from_the_queue_posts_to_the_existing_send_moadian_route(): void
    {
        $this->grant('invoices.moadian-queue.index', 'invoices.send-moadian');
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 5, 50)], true, ++$this->nextInvoiceNumber);
        $invoice = $this->sell([$this->productItem($product, 1, 100)], true, ++$this->nextInvoiceNumber)['invoice'];

        $this->get(route('invoices.moadian-queue.index'))
            ->assertOk()
            ->assertSee('action="'.route('invoices.send-moadian', $invoice).'"', false);
    }

    public function test_another_companys_unsent_invoices_never_appear(): void
    {
        $this->grant('invoices.moadian-queue.index');
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 5, 50)], true, ++$this->nextInvoiceNumber);
        $mine = $this->sell([$this->productItem($product, 1, 100)], true, ++$this->nextInvoiceNumber)['invoice'];

        $other = Company::factory()->create(['fiscal_year' => 1405]);
        config(['active-company-id' => $other->id]);
        $otherUser = User::factory()->create();
        $other->users()->syncWithoutDetaching($otherUser);
        $otherGroup = ProductGroup::factory()->withSubjects()->create(['company_id' => $other->id]);
        $otherCustomerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $other->id]);
        Customer::factory()->withGroup($otherCustomerGroup)->withSubject()->create(['company_id' => $other->id, 'name' => 'Other Corp']);
        config(['active-company-id' => $this->companyId]);

        $this->get(route('invoices.moadian-queue.index'))
            ->assertOk()
            ->assertSee(formatDocumentNumber($mine->number))
            ->assertDontSee('Other Corp');
    }
}
