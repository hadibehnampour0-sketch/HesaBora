<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * First version of Bank Reconciliation (roadmap P1 §6.2):
 * CSV import, manual matching against existing accounting Transactions,
 * duplicate-import detection, and match history via the line's own
 * reconciled_* fields. This service NEVER creates or posts a new
 * accounting Document/Transaction -- it only links an already-posted
 * Transaction to an imported statement line, so it cannot cause a
 * duplicate financial posting.
 */
class BankReconciliationService
{
    private const REQUIRED_COLUMNS = ['date', 'amount'];

    private const OPTIONAL_COLUMNS = ['description', 'reference'];

    public function importCsv(BankAccount $bankAccount, UploadedFile $file, User $user): BankStatement
    {
        $rows = $this->parse($file);

        return DB::transaction(function () use ($bankAccount, $file, $user, $rows) {
            $statement = BankStatement::create([
                'bank_account_id' => $bankAccount->id,
                'original_filename' => $file->getClientOriginalName(),
                'imported_by' => $user->id,
                'company_id' => getActiveCompany(),
            ]);

            $imported = 0;
            $duplicates = 0;

            foreach ($rows as $row) {
                $date = Carbon::parse($row['date'])->toDateString();
                $amount = (float) str_replace(',', '', (string) $row['amount']);
                $description = $row['description'] ?? null;
                $reference = $row['reference'] ?? null;
                $hash = hash('sha256', $date.'|'.number_format($amount, 2, '.', '').'|'.trim((string) $description).'|'.trim((string) $reference));

                $exists = BankStatementLine::where('bank_account_id', $bankAccount->id)
                    ->where('dedupe_hash', $hash)
                    ->exists();

                if ($exists) {
                    $duplicates++;

                    continue;
                }

                BankStatementLine::create([
                    'bank_statement_id' => $statement->id,
                    'bank_account_id' => $bankAccount->id,
                    'date' => $date,
                    'amount' => $amount,
                    'description' => $description,
                    'reference' => $reference,
                    'dedupe_hash' => $hash,
                ]);

                $imported++;
            }

            $statement->update([
                'lines_count' => $imported,
                'duplicate_lines_count' => $duplicates,
            ]);

            return $statement->fresh();
        });
    }

    /**
     * Unreconciled lines for a bank account, most recent first.
     */
    public function unmatchedLines(BankAccount $bankAccount)
    {
        return BankStatementLine::where('bank_account_id', $bankAccount->id)
            ->whereNull('reconciled_transaction_id')
            ->orderByDesc('date')
            ->get();
    }

    /**
     * Candidate unreconciled Transactions on this bank account's subject,
     * ranked by closeness of amount then date, for suggesting a manual match.
     */
    public function candidateTransactions(BankStatementLine $line, int $limit = 5)
    {
        $subjectId = $line->bankAccount->subject?->id;
        if (! $subjectId) {
            return collect();
        }

        $reconciledTransactionIds = BankStatementLine::whereNotNull('reconciled_transaction_id')->pluck('reconciled_transaction_id');

        return Transaction::query()
            ->where('subject_id', $subjectId)
            ->whereNotIn('id', $reconciledTransactionIds)
            ->get()
            ->sortBy(fn (Transaction $transaction) => abs(abs((float) $transaction->value) - abs((float) $line->amount)))
            ->take($limit)
            ->values();
    }

    /**
     * Concurrency (Workstream S, Phase 1): this used to check "already
     * reconciled?" / "already matched to another line?" via plain live
     * queries, then write -- with no `DB::transaction()` and no lock at
     * all, and `bank_statement_lines.reconciled_transaction_id` has no
     * unique constraint. Two DIFFERENT statement lines, matched to the
     * SAME transaction by two genuinely concurrent requests, would both
     * pass the "already used" check and both succeed -- double-counting
     * one real bank movement against two separate statement lines
     * (reproduced with two real MariaDB connections; see
     * tests/Feature/BankReconciliationMatchRaceTest.php). Fixed by
     * locking both the line and the transaction inside a real
     * transaction, so a second concurrent match is forced to wait behind
     * the first rather than reading stale "not yet used" state.
     */
    public function matchLine(BankStatementLine $line, Transaction $transaction, User $user): BankStatementLine
    {
        return DB::transaction(function () use ($line, $transaction, $user) {
            $lockedLine = BankStatementLine::query()->lockForUpdate()->findOrFail($line->id);
            $lockedTransaction = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if ($lockedLine->isReconciled()) {
                throw ValidationException::withMessages([
                    'line' => [__('This statement line is already reconciled.')],
                ]);
            }

            $alreadyUsed = BankStatementLine::where('reconciled_transaction_id', $lockedTransaction->id)->exists();
            if ($alreadyUsed) {
                throw ValidationException::withMessages([
                    'transaction_id' => [__('This transaction is already matched to another statement line.')],
                ]);
            }

            $lockedLine->update([
                'reconciled_transaction_id' => $lockedTransaction->id,
                'reconciled_at' => now(),
                'reconciled_by' => $user->id,
            ]);

            return $lockedLine->fresh();
        });
    }

    public function unmatchLine(BankStatementLine $line): BankStatementLine
    {
        $line->update([
            'reconciled_transaction_id' => null,
            'reconciled_at' => null,
            'reconciled_by' => null,
        ]);

        return $line->fresh();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parse(UploadedFile $file): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($file->getRealPath()));

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $header = fgetcsv($handle);
        if ($header === false || $header === null) {
            fclose($handle);

            throw ValidationException::withMessages(['file' => __('The statement file is empty or unreadable.')]);
        }

        $map = [];
        foreach ($header as $position => $label) {
            $key = strtolower(trim((string) $label));
            if (in_array($key, [...self::REQUIRED_COLUMNS, ...self::OPTIONAL_COLUMNS], true)) {
                $map[$key] = $position;
            }
        }

        foreach (self::REQUIRED_COLUMNS as $required) {
            if (! isset($map[$required])) {
                fclose($handle);

                throw ValidationException::withMessages(['file' => __('The statement file must contain at least "date" and "amount" columns.')]);
            }
        }

        $rows = [];
        while (($cols = fgetcsv($handle)) !== false) {
            if (count(array_filter($cols, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($map as $key => $position) {
                $row[$key] = $cols[$position] ?? null;
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}
