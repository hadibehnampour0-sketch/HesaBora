<?php

namespace Tests\Feature;

use App\Enums\SyncStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DeviceInstallation;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\User;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * D-071 decision 2 + decision 7: schema-foundation test for the shared Sync
 * Contract envelope (HasSyncEnvelope trait) on the 7 distributed entities.
 * This does not test any actual sync activity (that starts at 2.4/2.5) --
 * only that every listed entity gets a stable canonical UUID and a place to
 * record sync state, without disturbing existing behavior.
 */
class SyncEnvelopeTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9990;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;
        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        ProductGroup::factory()->withSubjects()->create(['name' => 'عمومی', 'vat' => 10, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'عمومی', 'description' => 'گروه مشتریان عمومی', 'company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);
    }

    private function uuidPattern(): string
    {
        return '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
    }

    public function test_customer_gets_a_canonical_uuid_and_default_sync_status(): void
    {
        $this->assertMatchesRegularExpression($this->uuidPattern(), $this->customer->uuid);
        $this->assertSame(SyncStatus::SYNCED, $this->customer->sync_status);
        $this->assertSame(1, $this->customer->sync_version);
    }

    public function test_subject_gets_a_canonical_uuid(): void
    {
        $subject = Subject::factory()->create(['company_id' => $this->companyId]);
        $this->assertMatchesRegularExpression($this->uuidPattern(), $subject->uuid);
    }

    public function test_two_customers_get_different_uuids(): void
    {
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);
        $other = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);

        $this->assertNotSame($this->customer->uuid, $other->uuid);
    }

    public function test_product_gets_a_canonical_uuid(): void
    {
        $product = $this->createProduct();
        $this->assertMatchesRegularExpression($this->uuidPattern(), $product->uuid);
    }

    public function test_document_invoice_payment_transaction_all_get_canonical_uuids(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 1, 500)], true, ++$this->nextInvoiceNumber);
        $sell = $this->sell([$this->productItem($product, 1, 1000)], true, ++$this->nextInvoiceNumber)['invoice'];
        $invoice = $this->findInvoice($sell->id);

        $this->assertMatchesRegularExpression($this->uuidPattern(), $invoice->uuid);
        $this->assertMatchesRegularExpression($this->uuidPattern(), $invoice->document->uuid);

        $cashBook = Subject::withoutGlobalScopes()->find((int) config('amir.cash_book'));
        $cashBox = Subject::factory()->withParent($cashBook)->create(['company_id' => $this->companyId]);

        $paymentService = app(\App\Services\PaymentService::class);
        $decision = $paymentService->createPayment($this->user, $invoice, [
            'amount' => 500,
            'subject_id' => $cashBox->id,
        ]);
        $this->assertFalse($decision->hasErrors(), $decision->toText());
        $payment = $invoice->payments()->latest('id')->firstOrFail();

        $this->assertMatchesRegularExpression($this->uuidPattern(), $payment->uuid);

        $transaction = $payment->document->transactions->first();
        $this->assertMatchesRegularExpression($this->uuidPattern(), $transaction->uuid);
    }

    public function test_mark_conflict_and_rejected_are_visible_states(): void
    {
        $this->customer->markConflict();
        $this->assertTrue($this->customer->fresh()->needsSyncReview());

        $this->customer->markRejected();
        $this->assertTrue($this->customer->fresh()->needsSyncReview());

        $this->customer->markSynced();
        $this->assertFalse($this->customer->fresh()->needsSyncReview());
    }

    public function test_origin_device_relation_resolves(): void
    {
        $installation = DeviceInstallation::create([
            'installation_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'device_key_hash' => bcrypt('secret'),
            'company_id' => $this->companyId,
            'status' => \App\Enums\DeviceInstallationStatus::ACTIVE,
        ]);

        $customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);
        $customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create([
            'company_id' => $this->companyId,
            'origin_device_id' => $installation->id,
        ]);

        $this->assertSame($installation->id, $customer->originDevice->id);
    }
}
