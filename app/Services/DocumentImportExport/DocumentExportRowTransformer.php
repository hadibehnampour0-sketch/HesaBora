<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

use App\Models\Document;
use App\Models\Transaction;

/**
 * Clean-Room Transformer for Document Export Rows.
 *
 * Formats model attributes and subject hierarchy according to accounting export conventions.
 *
 * License: MIT
 */
class DocumentExportRowTransformer
{
    public function extractRowsForDocument(Document $doc, array $orderedColumns): iterable
    {
        /** @var Transaction $trx */
        foreach ($doc->transactions as $trx) {
            $mapped = $this->transformTransaction($doc, $trx);
            $cells = [];
            foreach ($orderedColumns as $col) {
                $cells[] = $mapped[$col] ?? '';
            }
            yield $cells;
        }
    }

    public function transformTransaction(Document $doc, Transaction $trx): array
    {
        $subj = $trx->subject;
        [$cRoot, $cMoein, $cTafsili] = DakhlStandardImportFormat::parseSubjectCodes($subj?->code ?? '');

        return [
            'doc_number' => csvNumber(floor((float) $doc->number)),
            'doc_date' => formatDate($doc->date),
            'doc_title' => (string) ($doc->title ?? ''),
            'doc_type' => $doc->documentable ? __('automatic') : __('manual'),
            'doc_status' => __($doc->approved_at ? 'approved' : 'unapproved'),
            'subject_root_code' => $cRoot,
            'subject_moein_code' => $cMoein,
            'subject_tafsili_code' => $cTafsili,
            'subject_name' => (string) ($subj?->name ?? ''),
            'subject_type' => (string) ($subj?->type?->label() ?? ''),
            'subject_is_permanent' => __($subj?->is_permanent ? 'Permanent' : 'Temporary'),
            'transaction_desc' => (string) ($trx->desc ?? ''),
            'debit' => csvNumber($trx->debit ?? 0),
            'credit' => csvNumber($trx->credit ?? 0),
        ];
    }
}
