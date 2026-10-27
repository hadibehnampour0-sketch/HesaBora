<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service for auditable, non-destructive accounting document reversal and correction.
 * Implements Decision D-127 and Master Context §25.5 / Iranian Commercial Code compliance.
 */
class DocumentReversalService
{
    public const REASON_CLERICAL_ERROR = 'clerical_error';

    public const REASON_WRONG_ACCOUNT = 'wrong_account';

    public const REASON_INCORRECT_AMOUNT = 'incorrect_amount';

    public const REASON_DUPLICATE_ENTRY = 'duplicate_entry';

    public const REASON_VOIDED_INVOICE = 'voided_invoice';

    public const REASON_OTHER = 'other';

    /**
     * Standardized reason labels in Persian.
     *
     * @return array<string, string>
     */
    public static function getReasonLabels(): array
    {
        return [
            self::REASON_CLERICAL_ERROR => 'خطای ثبتی یا انشایی کاربر',
            self::REASON_WRONG_ACCOUNT => 'انتخاب نادرست کد یا سرفصل حساب',
            self::REASON_INCORRECT_AMOUNT => 'مبلغ یا بدهکار/بستانکار اشتباه',
            self::REASON_DUPLICATE_ENTRY => 'ثبت تکراری سند',
            self::REASON_VOIDED_INVOICE => 'ابطال یا لغو فاکتور مرتبط',
            self::REASON_OTHER => 'سایر موارد (با توضیحات)',
        ];
    }

    /**
     * Inspect dependencies and preconditions before reversal.
     *
     * @return array{can_reverse: bool, reasons: array<int, string>, dependencies: array<string, mixed>, preview_transactions: array<int, mixed>}
     */
    public function inspectDependencies(Document $document): array
    {
        $reasons = [];
        $canReverse = true;

        if (is_null($document->approved_at)) {
            $canReverse = false;
            $reasons[] = 'اسناد پیش‌نویس نیازی به ابطال ندارند و مستقیماً قابل ویرایش یا حذف هستند.';
        }

        if (! is_null($document->reversed_at) || Document::where('reverses_document_id', $document->id)->exists()) {
            $canReverse = false;
            $reasons[] = 'این سند قبلاً توسط سند دیگری ابطال شده است.';
        }

        if (! is_null($document->reverses_document_id)) {
            $canReverse = false;
            $reasons[] = 'سند ابطال را نمی‌توان مجدداً ابطال کرد.';
        }

        $dependencies = [
            'has_invoice' => ! is_null($document->invoice),
            'invoice_id' => $document->invoice?->id,
            'transaction_count' => $document->transactions()->count(),
            'total_debit' => (float) $document->transactions()->where('value', '>', 0)->sum('value'),
            'total_credit' => (float) abs($document->transactions()->where('value', '<', 0)->sum('value')),
        ];

        // Preview inverted transactions
        $previewTransactions = [];
        foreach ($document->transactions as $tx) {
            $previewTransactions[] = [
                'subject_id' => $tx->subject_id,
                'subject_name' => $tx->subject?->name,
                'original_value' => (float) $tx->value,
                'reversal_value' => -1 * (float) $tx->value,
                'desc' => $tx->desc,
            ];
        }

        return [
            'can_reverse' => $canReverse,
            'reasons' => $reasons,
            'dependencies' => $dependencies,
            'preview_transactions' => $previewTransactions,
        ];
    }

    /**
     * Reverse an approved accounting document.
     * Generates a new approved mirror Document whose transactions negate the original.
     *
     * @throws ValidationException
     */
    public function reverseDocument(
        Document $original,
        User $user,
        string $reasonCode,
        ?string $reason = null,
        ?string $operationId = null
    ): Document {
        return DB::transaction(function () use ($original, $user, $reasonCode, $reason, $operationId) {
            // Lock the original document row for update to prevent concurrent double-reversal
            /** @var Document $document */
            $document = Document::query()
                ->where('id', $original->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (is_null($document->approved_at)) {
                throw ValidationException::withMessages([
                    'document' => ['فقط اسناد تایید شده و نهایی قابلیت ابطال دارند.'],
                ]);
            }

            if (! is_null($document->reversed_at) || Document::where('reverses_document_id', $document->id)->exists()) {
                throw ValidationException::withMessages([
                    'document' => ['این سند قبلاً ابطال شده است و امکان ابطال مجدد آن وجود ندارد.'],
                ]);
            }

            if (! is_null($document->reverses_document_id)) {
                throw ValidationException::withMessages([
                    'document' => ['سند ابطال را نمی‌توان مجدداً ابطال کرد.'],
                ]);
            }

            $allowedReasons = array_keys(self::getReasonLabels());
            if (! in_array($reasonCode, $allowedReasons, true) && empty($reason)) {
                throw ValidationException::withMessages([
                    'reversal_reason_code' => ['علت ابطال سند باید به صورت دقیق مشخص شود.'],
                ]);
            }

            // Mirror transactions with exact opposite sign
            $reversalTransactions = [];
            $docNumber = $document->number ?? $document->id;
            foreach ($document->transactions()->get() as $tx) {
                $reversalTransactions[] = [
                    'subject_id' => $tx->subject_id,
                    'value' => -1 * (float) $tx->value,
                    'desc' => "ابطال سند شماره {$docNumber}: ".($tx->desc ?? ''),
                    'user_id' => $user->id,
                ];
            }

            // Create and approve the mirror reversal document
            $reversalData = [
                'title' => "سند ابطال سند شماره {$docNumber}".($document->title ? " ({$document->title})" : ''),
                'date' => now()->format('Y-m-d'),
                'company_id' => $document->company_id,
                'reverses_document_id' => $document->id,
                'reversal_reason_code' => $reasonCode,
                'reversal_reason' => $reason,
                'approved_at' => now()->format('Y-m-d'),
                'approver_id' => $user->id,
            ];

            $reversalDoc = DocumentService::createDocument($user, $reversalData, $reversalTransactions);

            // Update original document audit trail
            $document->reversed_at = now();
            $document->reversed_by_id = $user->id;
            $document->reversal_reason_code = $reasonCode;
            $document->reversal_reason = $reason;
            $document->save();

            // Activity Log
            if (function_exists('activity')) {
                activity()
                    ->performedOn($document)
                    ->causedBy($user)
                    ->withProperties([
                        'reversal_document_id' => $reversalDoc->id,
                        'reversal_number' => $reversalDoc->number,
                        'reason_code' => $reasonCode,
                        'reason' => $reason,
                        'operation_id' => $operationId,
                    ])
                    ->log('accounting.document.reversed');
            }

            return $reversalDoc;
        });
    }

    /**
     * "Correct This" Flow (اصلاح سند):
     * 1. Reverses the original approved document.
     * 2. Generates a new unapproved draft document pre-populated with original line items.
     * 3. Returns both models so the caller can redirect the clerk to edit the new draft.
     *
     * @return array{reversal: Document, draft: Document}
     *
     * @throws ValidationException
     */
    public function correctDocument(
        Document $original,
        User $user,
        string $reasonCode,
        ?string $reason = null
    ): array {
        return DB::transaction(function () use ($original, $user, $reasonCode, $reason) {
            $reversalDoc = $this->reverseDocument($original, $user, $reasonCode, $reason);

            // Prepare pre-filled transactions for the new draft
            $draftTransactions = [];
            foreach ($original->transactions()->get() as $tx) {
                $draftTransactions[] = [
                    'subject_id' => $tx->subject_id,
                    'value' => (float) $tx->value,
                    'desc' => $tx->desc,
                    'user_id' => $user->id,
                ];
            }

            $docNumber = $original->number ?? $original->id;
            $draftData = [
                'title' => "اصلاحیه سند شماره {$docNumber}".($original->title ? " ({$original->title})" : ''),
                'date' => now()->format('Y-m-d'),
                'company_id' => $original->company_id,
                // Deliberately unapproved: clerk must edit and submit for review
                'approved_at' => null,
                'approver_id' => null,
            ];

            $draftDoc = DocumentService::createDocument($user, $draftData, $draftTransactions);

            return [
                'reversal' => $reversalDoc,
                'draft' => $draftDoc,
            ];
        });
    }
}
