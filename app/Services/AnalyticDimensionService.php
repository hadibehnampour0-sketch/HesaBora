<?php

namespace App\Services;

use App\Enums\AnalyticDimensionType;
use App\Models\AnalyticDimension;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * P3.1: Analytic Dimensions. Tagging is deliberately orthogonal to the
 * financial ledger itself -- attaching/detaching a dimension on a
 * Transaction never touches `value`/`subject_id`/`document_id`, so it does
 * not fall under the posted-financial-record immutability rule (D-084)
 * that governs the transaction's actual amounts; a dimension can be
 * assigned or corrected on a transaction at any time, including long after
 * the document it belongs to was approved (the common real-world case: a
 * purchase is posted first, then assigned to a cost center during a later
 * review). No lockForUpdate() concurrency protection is needed here for
 * the same reason guardrail #25 identified for pure metadata: there is no
 * balance/total invariant a race could silently corrupt -- two concurrent
 * attach calls for different dimensions on the same transaction just both
 * succeed (the pivot's own unique constraint on
 * [analytic_dimension_id, transaction_id] is the only invariant, and a
 * unique-constraint violation is a safe, correct outcome, not a race bug).
 */
class AnalyticDimensionService
{
    public function create(array $data): AnalyticDimension
    {
        return AnalyticDimension::create([
            'type' => AnalyticDimensionType::tryFrom((int) $data['type']) ?? throw ValidationException::withMessages([
                'type' => [__('Invalid dimension type.')],
            ]),
            'code' => $data['code'],
            'name' => $data['name'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(AnalyticDimension $dimension, array $data): AnalyticDimension
    {
        $dimension->update([
            'code' => $data['code'] ?? $dimension->code,
            'name' => $data['name'] ?? $dimension->name,
            'is_active' => $data['is_active'] ?? $dimension->is_active,
        ]);

        return $dimension->fresh();
    }

    /**
     * Preserves the audit trail: a dimension already tagged on at least one
     * transaction cannot be deleted (its historical assignment would be
     * silently lost via the pivot's cascadeOnDelete). Deactivate instead.
     */
    public function delete(AnalyticDimension $dimension): void
    {
        if ($dimension->transactions()->exists()) {
            throw ValidationException::withMessages([
                'dimension' => [__('This dimension is already tagged on transactions and cannot be deleted. Deactivate it instead.')],
            ]);
        }

        $dimension->delete();
    }

    /**
     * @param  array<int, int>  $dimensionIds
     */
    public function syncOnTransaction(Transaction $transaction, array $dimensionIds): void
    {
        $validIds = AnalyticDimension::whereIn('id', $dimensionIds)->pluck('id')->all();

        if (count($validIds) !== count(array_unique($dimensionIds))) {
            throw ValidationException::withMessages([
                'dimensions' => [__('One or more selected dimensions do not belong to the active company.')],
            ]);
        }

        $transaction->analyticDimensions()->sync($validIds);
    }

    /**
     * Per-subject breakdown of every transaction tagged with a given
     * dimension -- answers "which account was affected, and by how much,
     * for this branch/project/cost-center/channel."
     *
     * @return Collection<int, array{subject_id: int, subject_name: string, total: float}>
     */
    public function subjectBreakdown(AnalyticDimension $dimension): Collection
    {
        return $dimension->transactions()
            ->with('subject:id,name,code')
            ->get()
            ->groupBy('subject_id')
            ->map(function (Collection $transactions, $subjectId) {
                $subject = $transactions->first()->subject;

                return [
                    'subject_id' => (int) $subjectId,
                    'subject_name' => $subject?->name ?? __('Unknown'),
                    'subject_code' => $subject?->code,
                    'total' => (float) $transactions->sum('value'),
                ];
            })
            ->values();
    }

    public function listByType(AnalyticDimensionType $type): Collection
    {
        return AnalyticDimension::where('type', $type)->orderBy('code')->get();
    }
}
