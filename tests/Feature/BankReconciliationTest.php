<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\ProductGroup;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BankReconciliationService;
use App\Services\PaymentService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class BankReconciliationTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 9700;

    protected PaymentService $paymentService;

    protected BankReconciliationService $reconciliationService;

    protected BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentService = app(PaymentService::class);
        $this->reconciliationService = app(BankReconciliationService::class);

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;

        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'bank-reconciliation.*']));
        $this->actingAs($this->user);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        ProductGroup::factory()->withSubjects()->create(['name' => 'عمومی', 'vat' => 10, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'عمومی', 'description' => 'گروه مشتریان عمومی', 'company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);

        $bank = new Bank(['name' => 'بانک نمونه']);
        $bank->company_id = $this->companyId;
        $bank->save();

        $this->bankAccount = BankAccount::factory()->withSubject()->create([
            'company_id' => $this->companyId,
            'bank_id' => $bank->id,
        ]);
    }

    private function approvedSell(float $unit, int $qty): \App\Models\Invoice
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, $qty, $unit * 0.5)], true, ++$this->nextInvoiceNumber);
        $sell = $this->sell([$this->productItem($product, $qty, $unit)], true, ++$this->nextInvoiceNumber)['invoice'];

        return $this->findInvoice($sell->id);
    }

    private function csvFile(string $content, string $name = 'statement.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'stmt');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    public function test_importing_a_csv_creates_statement_lines(): void
    {
        $csv = "date,amount,description,reference\n2026-08-01,1000.00,Deposit,REF1\n2026-08-02,-500.00,Withdrawal,REF2\n";

        $statement = $this->reconciliationService->importCsv($this->bankAccount, $this->csvFile($csv), $this->user);

        $this->assertEquals(2, $statement->lines_count);
        $this->assertEquals(0, $statement->duplicate_lines_count);
        $this->assertCount(2, $this->reconciliationService->unmatchedLines($this->bankAccount));
    }

    public function test_reimporting_the_same_rows_is_detected_as_duplicate(): void
    {
        $csv = "date,amount,description,reference\n2026-08-01,1000.00,Deposit,REF1\n";

        $this->reconciliationService->importCsv($this->bankAccount, $this->csvFile($csv), $this->user);
        $second = $this->reconciliationService->importCsv($this->bankAccount, $this->csvFile($csv), $this->user);

        $this->assertEquals(0, $second->lines_count);
        $this->assertEquals(1, $second->duplicate_lines_count);
        $this->assertCount(1, BankStatementLine::all());
    }

    public function test_matching_a_line_links_it_to_a_transaction_without_posting_a_new_one(): void
    {
        $sell = $this->approvedSell(1000, 1);
        $payment = $this->paymentService->createCreditPayment($this->user, $this->customer, [
            'amount' => 1000,
            'subject_id' => $this->bankAccount->subject->id,
        ]);
        $this->paymentService->allocatePayment($this->user, $payment, $sell, 1000);

        $bankTx = Transaction::where('subject_id', $this->bankAccount->subject->id)->firstOrFail();
        $transactionCountBefore = Transaction::count();

        $csv = "date,amount\n2026-08-01,1000.00\n";
        $statement = $this->reconciliationService->importCsv($this->bankAccount, $this->csvFile($csv), $this->user);
        $line = $statement->lines()->firstOrFail();

        $matched = $this->reconciliationService->matchLine($line, $bankTx, $this->user);

        $this->assertTrue($matched->isReconciled());
        $this->assertEquals($bankTx->id, $matched->reconciled_transaction_id);
        $this->assertEquals($transactionCountBefore, Transaction::count());
        $this->assertCount(0, $this->reconciliationService->unmatchedLines($this->bankAccount));
    }

    public function test_candidate_transactions_are_ranked_by_amount_closeness(): void
    {
        $sellA = $this->approvedSell(1000, 1);
        $sellB = $this->approvedSell(1200, 1);

        $paymentA = $this->paymentService->createCreditPayment($this->user, $this->customer, ['amount' => 1000, 'subject_id' => $this->bankAccount->subject->id]);
        $this->paymentService->allocatePayment($this->user, $paymentA, $sellA, 1000);

        $paymentB = $this->paymentService->createCreditPayment($this->user, $this->customer, ['amount' => 1200, 'subject_id' => $this->bankAccount->subject->id]);
        $this->paymentService->allocatePayment($this->user, $paymentB, $sellB, 1200);

        $csv = "date,amount\n2026-08-01,1210.00\n";
        $statement = $this->reconciliationService->importCsv($this->bankAccount, $this->csvFile($csv), $this->user);
        $line = $statement->lines()->firstOrFail();

        $candidates = $this->reconciliationService->candidateTransactions($line);

        $this->assertEqualsWithDelta(1200, abs((float) $candidates->first()->value), 0.01);
    }

    public function test_cannot_match_an_already_reconciled_line(): void
    {
        $sell = $this->approvedSell(1000, 1);
        $payment = $this->paymentService->createCreditPayment($this->user, $this->customer, ['amount' => 1000, 'subject_id' => $this->bankAccount->subject->id]);
        $this->paymentService->allocatePayment($this->user, $payment, $sell, 1000);
        $bankTx = Transaction::where('subject_id', $this->bankAccount->subject->id)->firstOrFail();

        $csv = "date,amount\n2026-08-01,1000.00\n";
        $statement = $this->reconciliationService->importCsv($this->bankAccount, $this->csvFile($csv), $this->user);
        $line = $statement->lines()->firstOrFail();

        $this->reconciliationService->matchLine($line, $bankTx, $this->user);

        $this->expectException(ValidationException::class);
        $this->reconciliationService->matchLine($line->fresh(), $bankTx, $this->user);
    }

    public function test_cannot_match_a_transaction_already_used_by_another_line(): void
    {
        $sell = $this->approvedSell(1000, 1);
        $payment = $this->paymentService->createCreditPayment($this->user, $this->customer, ['amount' => 1000, 'subject_id' => $this->bankAccount->subject->id]);
        $this->paymentService->allocatePayment($this->user, $payment, $sell, 1000);
        $bankTx = Transaction::where('subject_id', $this->bankAccount->subject->id)->firstOrFail();

        $csv = "date,amount\n2026-08-01,1000.00\n2026-08-02,999.00\n";
        $statement = $this->reconciliationService->importCsv($this->bankAccount, $this->csvFile($csv), $this->user);
        [$lineA, $lineB] = $statement->lines()->orderBy('id')->get()->all();

        $this->reconciliationService->matchLine($lineA, $bankTx, $this->user);

        $this->expectException(ValidationException::class);
        $this->reconciliationService->matchLine($lineB, $bankTx, $this->user);
    }

    public function test_unmatching_a_line_restores_it_to_unmatched(): void
    {
        $sell = $this->approvedSell(1000, 1);
        $payment = $this->paymentService->createCreditPayment($this->user, $this->customer, ['amount' => 1000, 'subject_id' => $this->bankAccount->subject->id]);
        $this->paymentService->allocatePayment($this->user, $payment, $sell, 1000);
        $bankTx = Transaction::where('subject_id', $this->bankAccount->subject->id)->firstOrFail();

        $csv = "date,amount\n2026-08-01,1000.00\n";
        $statement = $this->reconciliationService->importCsv($this->bankAccount, $this->csvFile($csv), $this->user);
        $line = $statement->lines()->firstOrFail();
        $this->reconciliationService->matchLine($line, $bankTx, $this->user);

        $this->reconciliationService->unmatchLine($line->fresh());

        $this->assertCount(1, $this->reconciliationService->unmatchedLines($this->bankAccount));
    }

    public function test_reconciliation_page_and_import_route_work_end_to_end(): void
    {
        $this->get(route('bank-reconciliation.index', $this->bankAccount))->assertOk();

        $csv = "date,amount,description\n2026-08-01,1000.00,Deposit\n";
        $response = $this->post(route('bank-reconciliation.import', $this->bankAccount), [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertRedirect(route('bank-reconciliation.index', $this->bankAccount));
        $this->assertCount(1, BankStatementLine::all());
    }
}
