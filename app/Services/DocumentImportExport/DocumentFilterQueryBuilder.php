<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

use App\Models\Document;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;

/**
 * Clean-Room Query Builder for Accounting Document Export & Filtering.
 *
 * Compiles search and reporting parameters into optimized Eloquent constraints.
 *
 * License: MIT
 */
class DocumentFilterQueryBuilder
{
    public function compile(array $filters): Builder
    {
        $target = Document::query()->orderBy('date')->orderBy('number');

        $this->applyIdentifierFilters($target, $filters);
        $this->applyChronologicalFilters($target, $filters);
        $this->applyStatusAndTextFilters($target, $filters);
        $this->applyAccountHierarchyFilters($target, $filters);

        return $target;
    }

    protected function applyIdentifierFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['number'])) {
            $query->where('number', convertToFloat($filters['number']));
        }

        if (! empty($filters['start_document_number'])) {
            $query->where('number', '>=', $filters['start_document_number']);
        }

        if (! empty($filters['end_document_number'])) {
            $query->where('number', '<=', $filters['end_document_number']);
        }
    }

    protected function applyChronologicalFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['date'])) {
            $query->where('date', convertToGregorian($filters['date']));
        }

        if (! empty($filters['start_date'])) {
            $query->where('date', '>=', jalali_to_gregorian_date($filters['start_date']));
        }

        if (! empty($filters['end_date'])) {
            $query->where('date', '<=', jalali_to_gregorian_date($filters['end_date']));
        }
    }

    protected function applyStatusAndTextFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['text'])) {
            $needle = $filters['text'];
            $query->where(function (Builder $nested) use ($needle): void {
                $nested->where('title', 'like', '%'.$needle.'%')
                    ->orWhereHas('transactions', function (Builder $trxQuery) use ($needle): void {
                        $trxQuery->where('desc', 'like', '%'.$needle.'%');
                    });
            });
        }

        $status = $filters['status'] ?? null;
        if ($status === 'approved') {
            $query->whereNotNull('approved_at');
        } elseif ($status === 'unapproved') {
            $query->whereNull('approved_at');
        }
    }

    protected function applyAccountHierarchyFilters(Builder $query, array $filters): void
    {
        if (empty($filters['subject_id'])) {
            return;
        }

        $accountNode = Subject::find($filters['subject_id']);
        if ($accountNode instanceof Subject) {
            $descendants = $accountNode->getAllDescendantIds();
            $query->whereHas('transactions', function (Builder $sub) use ($descendants): void {
                $sub->whereIn('subject_id', $descendants);
            });
        }
    }
}
