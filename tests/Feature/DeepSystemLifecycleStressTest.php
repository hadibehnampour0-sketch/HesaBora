<?php

namespace Tests\Feature;

use App\Enums\ChequeType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class DeepSystemLifecycleStressTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    protected User $adminUser;
    protected Company $company;
    protected int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create([
            'name' => 'شرکت بازرگانی تست عمیق دخل',
            'fiscal_year' => 1405,
        ]);
        $this->companyId = $this->company->id;

        $this->adminUser = User::factory()->create([
            'name' => 'مدیر حسابداری',
            'email' => 'admin@test-dakhl.ir',
        ]);
        $this->adminUser->companies()->attach($this->companyId);

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $role = Role::firstOrCreate(['name' => 'Super-Admin', 'guard_name' => 'web']);
        $this->adminUser->assignRole($role);
        $this->adminUser->givePermissionTo(Permission::all());

        $this->withCookies(['active-company-id' => (string) $this->companyId]);
        $_COOKIE['active-company-id'] = (string) $this->companyId;
        config(['active-company-id' => $this->companyId, 'active-company-fiscal-year' => 1405]);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        foreach (['cheque_documents_receivable', 'cheque_documents_in_collection', 'cheque_documents_payable'] as $key) {
            $subj = Subject::factory()->create(['company_id' => $this->companyId]);
            config(['amir.'.$key => (string) $subj->id]);
            \App\Models\Config::create([
                'type' => 3,
                'category' => 1,
                'key' => $key,
                'value' => (string) $subj->id,
                'desc' => $key,
                'company_id' => $this->companyId,
            ]);
        }
    }

    /**
     * 1. Test Invoice Creation, Approval, Posting, and Balanced Document Invariant
     */
    public function test_invoice_creation_approval_and_posting_invariants(): void
    {
        $warehouse = Warehouse::create([
            'company_id' => $this->companyId,
            'name' => 'انبار مرکزی',
            'code' => 'WH-01',
        ]);

        $group = ProductGroup::factory()->withSubjects()->create(['name' => 'کالای تست', 'company_id' => $this->companyId]);
        $product = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'P100',
            'name' => 'کالای تجاری تست ۱',
            'quantity' => 50,
            'average_cost' => 1000000,
            'selling_price' => 1500000,
            'oversell' => 1,
        ]);
        \App\Models\WarehouseProductStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 50,
        ]);

        $customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);
        $customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create([
            'company_id' => $this->companyId,
            'name' => 'شرکت خریدار آریا',
            'mobile' => '09121234567',
        ]);

        // Submit Sell Invoice form
        $res = $this->actingAs($this->adminUser)
            ->withSession(['company_id' => $this->companyId, 'fiscal_year' => 1405])
            ->post(route('invoices.store'), [
                'invoice_type' => 'sell',
                'customer_id' => $customer->id,
                'warehouse_id' => $warehouse->id,
                'date' => Carbon::now()->format('Y/m/d'),
                'document_number' => 101,
                'invoice_number' => 201,
                'transactions' => [
                    [
                        'item_id' => 'product-'.$product->id,
                        'quantity' => 5,
                        'unit' => 1500000,
                        'total' => 7500000,
                        'off' => 0,
                        'vat' => 0,
                    ],
                ],
            ]);

        $res->assertSessionHasNoErrors();

        $invoice = Invoice::where('customer_id', $customer->id)->latest()->first();
        $this->assertNotNull($invoice, 'Invoice must be created');
        $this->assertEquals(InvoiceType::SELL, $invoice->invoice_type);

        // Approve invoice via changeStatus
        $approveRes = $this->actingAs($this->adminUser)
            ->withSession(['company_id' => $this->companyId, 'fiscal_year' => 1405])
            ->post(route('invoices.change-status', ['invoice' => $invoice, 'status' => 'approved']));

        $approveRes->assertSessionHasNoErrors();
        $invoice->refresh();

        $this->assertEquals(InvoiceStatus::APPROVED, $invoice->status);
        $this->assertNotNull($invoice->document_id, 'Approved invoice must have an associated accounting Document');

        // Check Document balance (SUM(value) == 0)
        $doc = Document::find($invoice->document_id);
        $this->assertNotNull($doc);
        $this->assertEquals(0, (float) $doc->transactions()->sum('value'), 'Accounting Document for invoice must be strictly balanced');
    }

    /**
     * 2. Test Accounting Manual Document Posting and Balanced Invariant
     */
    public function test_manual_accounting_document_balance_invariant(): void
    {
        $subjects = Subject::where('company_id', $this->companyId)->whereNotNull('parent_id')->take(2)->get();
        $this->assertCount(2, $subjects, 'Need at least 2 child subjects');

        // Balanced document should succeed
        $res = $this->actingAs($this->adminUser)
            ->withSession(['company_id' => $this->companyId, 'fiscal_year' => 1405])
            ->post(route('documents.store'), [
                'date' => Carbon::now()->format('Y/m/d'),
                'number' => 55,
                'description' => 'سند دستی متوازن تست',
                'transactions' => [
                    [
                        'subject_id' => $subjects[0]->id,
                        'desc' => 'ردیف بدهکار',
                        'debit' => 5000000,
                        'credit' => 0,
                    ],
                    [
                        'subject_id' => $subjects[1]->id,
                        'desc' => 'ردیف بستانکار',
                        'debit' => 0,
                        'credit' => 5000000,
                    ],
                ],
            ]);

        $res->assertSessionHasNoErrors();
        $this->assertDatabaseHas('documents', [
            'company_id' => $this->companyId,
            'number' => 55,
        ]);
    }

    /**
     * 3. Test Cheque Lifecycle (Register -> Deposit -> Clear)
     */
    public function test_cheque_lifecycle_transitions(): void
    {
        $bank = Bank::firstOrCreate(['name' => 'بانک تجارت', 'company_id' => $this->companyId]);
        $bankSubject = Subject::factory()->create(['company_id' => $this->companyId]);
        $bankAccount = BankAccount::create([
            'company_id' => $this->companyId,
            'bank_id' => $bank->id,
            'number' => '1122334455',
            'type' => \App\Enums\BankAccountType::CURRENT->value,
            'name' => 'حساب جاری تجارت',
            'subject_id' => $bankSubject->id,
        ]);

        $customer = Customer::create([
            'company_id' => $this->companyId,
            'name' => 'مشتری چک دار',
            'mobile' => '09129998877',
        ]);

        // Register Cheque
        $cheque = Cheque::create([
            'company_id' => $this->companyId,
            'direction' => ChequeType::RECEIVABLE->value,
            'purpose' => ChequeType::SETTLEMENT->value,
            'amount' => 50000000,
            'write_date' => Carbon::now()->format('Y-m-d'),
            'due_date' => Carbon::now()->addDays(15)->format('Y-m-d'),
            'cheque_number' => '998801',
            'sayad_number' => '5544332211009988',
            'status' => ChequeType::REGISTERED->value,
            'customer_id' => $customer->id,
        ]);

        $this->assertNotNull($cheque->id);

        // Transition: Deposit to bank account
        $depositRes = $this->actingAs($this->adminUser)
            ->withSession(['company_id' => $this->companyId, 'fiscal_year' => 1405])
            ->post(route('cheques.transition', ['cheque' => $cheque->id, 'action' => 'deposit']), [
                'bank_account_id' => $bankAccount->id,
                'date' => Carbon::now()->format('Y-m-d'),
            ]);

        $depositRes->assertSessionHasNoErrors();
        $cheque->refresh();
        $this->assertEquals(ChequeType::DEPOSITED, $cheque->status);

        // Transition: Clear Cheque
        $clearRes = $this->actingAs($this->adminUser)
            ->withSession(['company_id' => $this->companyId, 'fiscal_year' => 1405])
            ->post(route('cheques.transition', ['cheque' => $cheque->id, 'action' => 'clear']), [
                'date' => Carbon::now()->format('Y-m-d'),
            ]);

        $clearRes->assertSessionHasNoErrors();
        $cheque->refresh();
        $this->assertEquals(ChequeType::CLEARED, $cheque->status);
    }

    /**
     * 4. Test Warehouse Stock Transfer
     */
    public function test_warehouse_transfer_integrity(): void
    {
        $whSource = Warehouse::create(['company_id' => $this->companyId, 'name' => 'انبار مبدا', 'code' => 'WH-SRC']);
        $whDest = Warehouse::create(['company_id' => $this->companyId, 'name' => 'انبار مقصد', 'code' => 'WH-DST']);

        $group = ProductGroup::create(['name' => 'کالای انبار', 'company_id' => $this->companyId]);
        $product = Product::create([
            'company_id' => $this->companyId,
            'group' => $group->id,
            'code' => 'PRD-TRANSFER',
            'name' => 'کالای انتقالی بین انبارها',
            'quantity' => 100,
            'average_cost' => 200000,
            'selling_price' => 300000,
            'oversell' => 1,
        ]);
        \App\Models\WarehouseProductStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $whSource->id,
            'quantity' => 100,
        ]);

        $transferRes = $this->actingAs($this->adminUser)
            ->withSession(['company_id' => $this->companyId, 'fiscal_year' => 1405])
            ->post(route('warehouses.transfer.store'), [
                'from_warehouse_id' => $whSource->id,
                'to_warehouse_id' => $whDest->id,
                'product_id' => $product->id,
                'quantity' => 10,
                'description' => 'حواله انتقال ۱۰ عدد کالا به انبار مقصد',
            ]);

        $transferRes->assertSessionHasNoErrors();
        $this->assertDatabaseHas('warehouse_transfers', [
            'company_id' => $this->companyId,
            'from_warehouse_id' => $whSource->id,
            'to_warehouse_id' => $whDest->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);
    }

    /**
     * 5. Test Quick Payment Receive & Pay
     */
    public function test_quick_payment_receive_and_pay(): void
    {
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);
        $customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create([
            'company_id' => $this->companyId,
            'name' => 'مشتری تسویه سریع',
            'mobile' => '09124443322',
        ]);

        $group = ProductGroup::factory()->withSubjects()->create(['name' => 'کالای تست تسویه', 'company_id' => $this->companyId]);
        $product = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'P-QUICK',
            'name' => 'کالای تسویه سریع',
            'quantity' => 10,
            'average_cost' => 10000000,
            'selling_price' => 12500000,
            'oversell' => 1,
        ]);
        $warehouse = Warehouse::firstOrCreate(['company_id' => $this->companyId, 'code' => 'WH-QP'], ['name' => 'انبار تسویه']);
        \App\Models\WarehouseProductStock::firstOrCreate(['product_id' => $product->id, 'warehouse_id' => $warehouse->id], ['quantity' => 10]);

        $invoice = Invoice::create([
            'company_id' => $this->companyId,
            'invoice_type' => InvoiceType::SELL,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'number' => 8801,
            'date' => Carbon::now()->format('Y-m-d'),
            'status' => InvoiceStatus::APPROVED,
            'amount' => 12500000,
            'vat' => 0,
        ]);
        $invoice->items()->create([
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 12500000,
            'amount' => 12500000,
            'total' => 12500000,
            'vat' => 0,
            'unit_discount' => 0,
        ]);

        $cashBook = Subject::withoutGlobalScopes()->find((int) config('amir.cash_book'));
        $subject = Subject::firstOrCreate(['name' => 'صندوق مرکزی تسویه', 'company_id' => $this->companyId], ['code' => '011099', 'parent_id' => $cashBook?->id, 'type' => 3]);

        // Receive payment via Quick Payment
        $resReceive = $this->actingAs($this->adminUser)
            ->withSession(['company_id' => $this->companyId, 'fiscal_year' => 1405])
            ->post(route('quick-payment.store', 'receive'), [
                'party_id' => $customer->id,
                'subject_id' => $subject->id,
                'amount' => 12500000,
                'date' => Carbon::now()->format('Y/m/d'),
                'description' => 'دریافت نقدی سریع بابت مطالبات',
            ]);

        $resReceive->assertSessionHasNoErrors();

        // Check Payment created
        $payment = Payment::where('payer_id', $customer->id)->latest()->first();
        $this->assertNotNull($payment);
        $this->assertEquals(12500000, $payment->amount);
    }
}
