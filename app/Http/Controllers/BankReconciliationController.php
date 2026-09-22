<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\Transaction;
use App\Services\BankReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BankReconciliationController extends Controller
{
    public function __construct(private readonly BankReconciliationService $service) {}

    public function index(BankAccount $bankAccount)
    {
        $lines = $this->service->unmatchedLines($bankAccount);
        $candidatesByLine = $lines->mapWithKeys(fn (BankStatementLine $line) => [
            $line->id => $this->service->candidateTransactions($line),
        ]);

        return view('bank-reconciliation.index', [
            'bankAccount' => $bankAccount,
            'lines' => $lines,
            'candidatesByLine' => $candidatesByLine,
        ]);
    }

    public function import(Request $request, BankAccount $bankAccount)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $statement = $this->service->importCsv($bankAccount, $validated['file'], $request->user());

        return redirect()->route('bank-reconciliation.index', $bankAccount)
            ->with('success', __(':imported lines imported, :duplicates duplicates skipped.', [
                'imported' => $statement->lines_count,
                'duplicates' => $statement->duplicate_lines_count,
            ]));
    }

    public function match(Request $request, BankStatementLine $line)
    {
        $this->ensureLineOwnedByActiveCompany($line);

        $validated = $request->validate([
            'transaction_id' => [
                'required',
                'integer',
                Rule::exists('transactions', 'id'),
            ],
        ]);

        $transaction = Transaction::findOrFail($validated['transaction_id']);
        abort_unless($transaction->document?->company_id === getActiveCompany(), 404);

        $this->service->matchLine($line, $transaction, $request->user());

        return redirect()->route('bank-reconciliation.index', $line->bank_account_id)
            ->with('success', __('Statement line matched successfully.'));
    }

    public function unmatch(BankStatementLine $line)
    {
        $this->ensureLineOwnedByActiveCompany($line);

        $this->service->unmatchLine($line);

        return redirect()->route('bank-reconciliation.index', $line->bank_account_id)
            ->with('success', __('Match removed successfully.'));
    }

    /**
     * `bank_statement_lines` has no `company_id`/`FiscalYearScope` of its
     * own (only reachable via `bank_account_id` -> `BankAccount`, which IS
     * scoped) -- found adversarially while auditing the already-implemented
     * payment-allocation/bank-reconciliation/ageing layer (Epic Pack 2).
     * `match()`'s `transaction_id` also comes from the request BODY, not a
     * route parameter, so it needed its own explicit check too, mirroring
     * TransactionController::show()'s established `$transaction->document
     * ?->company_id` pattern.
     */
    private function ensureLineOwnedByActiveCompany(BankStatementLine $line): void
    {
        abort_unless(BankAccount::whereKey($line->bank_account_id)->exists(), 404);
    }
}
