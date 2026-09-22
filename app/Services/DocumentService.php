<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DocumentService
{
    /**
     * Create a new document.
     *
     * Concurrency (Workstream S, Phase 2): the number fallback used to be
     * `Document::max('number') + 1`, computed OUTSIDE any transaction and
     * with no lock -- and `documents.number` has no unique constraint at
     * all. Two genuinely concurrent creations (this fallback is hit on
     * EVERY credit payment and direct invoice payment, since
     * PaymentService always passes `'number' => null`) reading the same
     * MAX before either commits would silently produce two accounting
     * documents with the IDENTICAL reference number -- no error, no
     * collision, just wrong data (reproduced with two real MariaDB
     * connections; see tests/Feature/DocumentNumberingRaceTest.php).
     * Fixed the same way `AncillaryCostService::
     * nextAncillaryCostNumberForUpdate()` already protects ITS OWN
     * numbering sequence: lock the current-max row inside the same
     * transaction that inserts the new one.
     *
     * @return Document
     */
    public static function createDocument(User $user, array $data, array $transactions)
    {

        $validator = Validator::make($data, [
            'number' => 'nullable|decimal:0,2',
            'title' => 'nullable|string',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data['creator_id'] = $user->id;
        $data['company_id'] = isset($data['company_id']) ? $data['company_id'] : getActiveCompany();

        $document = null;
        DB::transaction(function () use (&$data, $transactions, $user, &$document) {
            if (empty($data['number'])) {
                $data['number'] = self::nextDocumentNumberForUpdate();
            }

            // The documentable association (if any) is set on the model
            // BEFORE the initial save -- not as a second save afterward --
            // so a Document's very first `created` event already reflects
            // its true final shape (manual draft vs. automatic/linked).
            // This matters for D-073 stage 2.7A's Document Draft Sync,
            // which decides sync-eligibility from that event.
            $document = new Document($data);
            if (! empty($data['documentable'] ?? null)) {
                $document->documentable()->associate($data['documentable']);
            }
            $document->save();

            if (! empty($data['approved_at']) && ! empty($data['approver_id'])) {
                self::approveDocument($user, $document);
            }
            foreach ($transactions as $transactionData) {
                DocumentService::createTransaction($document, $transactionData);
            }
        });

        return $document;
    }

    /**
     * Locks the current highest-numbered document (scoped to the active
     * company, same as the FiscalYearScope-filtered `Document::max()` this
     * replaces) so a genuinely concurrent caller is forced to wait behind
     * this transaction rather than reading the same stale max. Like
     * AncillaryCostService's equivalent, this does not protect the very
     * first document ever created for a company (no row yet to lock) --
     * an accepted, narrow edge case matching that existing precedent.
     */
    private static function nextDocumentNumberForUpdate(): float
    {
        $last = Document::query()
            ->whereNotNull('number')
            ->orderByDesc('number')
            ->lockForUpdate()
            ->first();

        return ((float) ($last?->number ?? 0)) + 1;
    }

    private static function approveDocument(User $user, Document $document): void
    {
        $sum = $document->transactions()->sum('value');

        if (abs($sum) > 0.005) {
            throw ValidationException::withMessages([
                'transactions' => [__('The sum of transactions must be zero.')],
            ]);
        }

        $document->approved_at = now();
        $document->approver_id = $user->id;
        $document->save();
    }

    private static function unapproveDocument(User $user, Document $document): void
    {
        $document->approved_at = null;
        $document->approver_id = $user->id;
        $document->save();
    }

    /**
     * Associate a document with a documentable entity.
     */
    public static function syncDocumentable(Document $document, $documentable = null): void
    {
        $document->documentable()->associate($documentable);
        if ($document->isDirty(['documentable_id', 'documentable_type'])) {
            $document->save();
        }
    }

    /**
     * Update an existing document.
     *
     * @return Document
     */
    public static function updateDocument(Document $document, array $data)
    {
        $validator = Validator::make($data, [
            'number' => 'nullable|decimal:0,2',
            'title' => 'nullable|string|min:3|max:255',
            'date' => 'date',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $document->fill($data);

        self::syncDocumentable($document, $data['documentable'] ?? null);

        $document->save();

        return $document;
    }

    /**
     * Create a new transaction for a document.
     *
     * @return Transaction
     */
    public static function createTransaction(Document $document, array $data)
    {
        $validator = Validator::make($data, [
            'subject_id' => 'required|integer',
            'desc' => 'nullable|string',
            'value' => 'required|decimal:0,2',
            'created_at' => 'nullable|date',
            'updated_at' => 'nullable|date',
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $transaction = new Transaction;
        $data['user_id'] ??= Auth::id();
        $transaction->fill($data);
        $transaction->document_id = $document->id;
        $transaction->save();

        return $transaction;
    }

    /**
     * Update an existing transaction.
     *
     * @return Transaction
     */
    public static function updateTransaction(Transaction $transaction, array $data)
    {
        $validator = Validator::make($data, [
            'subject_id' => 'integer',
            'user_id' => 'integer',
            'desc' => 'string',
            'value' => 'integer',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $transaction->fill($data);
        $transaction->save();

        return $transaction;
    }

    public static function updateDocumentTransactions(int $documentId, array $transactionsData): void
    {
        DB::beginTransaction();

        $existingTransactionIds = [];
        foreach ($transactionsData as $transactionData) {
            $transaction = Transaction::updateOrCreate(
                ['id' => $transactionData['transaction_id'] ?? null],
                [
                    'document_id' => $documentId,
                    'subject_id' => $transactionData['subject_id'],
                    'desc' => $transactionData['desc'],
                    'value' => floatval($transactionData['value']),
                ]
            );
            $existingTransactionIds[] = $transaction->id;
        }
        app(ActivityLogService::class)->deleteModels(
            Transaction::where('document_id', $documentId)->whereNotIn('id', $existingTransactionIds)
        );

        DB::commit();
    }

    public static function deleteDocument(int $documentId): void
    {
        $documentFileService = new DocumentFileService;
        DB::transaction(function () use ($documentId, $documentFileService) {
            $documentFiles = DocumentFile::where('document_id', $documentId)->get();
            foreach ($documentFiles as $documentFile) {
                $documentFileService->delete($documentFile);
            }

            app(ActivityLogService::class)->deleteModels(Transaction::where('document_id', $documentId));
            app(ActivityLogService::class)->deleteModels(Document::where('id', $documentId));
        });
    }

    public static function changeDocumentStatus(Document $document, User $user, string $status): void
    {
        DB::transaction(function () use ($document, $user, $status) {
            match ($status) {
                'approved' => self::approveDocument($user, $document),
                'unapproved' => self::unapproveDocument($user, $document),
                default => throw ValidationException::withMessages(['status' => __('Invalid status')]),
            };
        });
    }

    /**
     * Approve all unapproved documents that have balanced transactions (sum = 0).
     *
     * @return array{approved: int, skipped: int}
     */
    public static function approveAll(User $user): array
    {
        $approved = 0;
        $skipped = 0;

        Document::whereNull('approved_at')
            ->with('transactions')
            ->each(function (Document $document) use ($user, &$approved, &$skipped) {
                $sum = $document->transactions->sum('value');
                if (abs($sum) > 0.005) {
                    $skipped++;

                    return;
                }

                DB::transaction(function () use ($document, $user) {
                    $document->approved_at = now();
                    $document->approver_id = $user->id;
                    $document->save();
                });

                $approved++;
            });

        return ['approved' => $approved, 'skipped' => $skipped];
    }
}
