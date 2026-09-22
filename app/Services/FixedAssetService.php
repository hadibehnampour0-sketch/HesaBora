<?php

namespace App\Services;

use App\Enums\FixedAssetStatus;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciationEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P3.5 (Fixed Assets). Straight-line depreciation only. Accumulated
 * depreciation is always computed live from
 * fixed_asset_depreciation_entries (never stored/denormalized on the
 * asset), matching D-070's "no value that can drift out of sync" rule.
 * postDepreciation() posts a real, balanced accounting document (Dr
 * Depreciation Expense, Cr Accumulated Depreciation) -- this is a REAL
 * financial event, unlike Settlement's obligation/schedule creation, so
 * (unlike SettlementService::createInstallmentPlan()) there is no
 * "metadata only, no document" step here to keep separate.
 */
class FixedAssetService
{
    public function register(array $data, User $user): FixedAsset
    {
        if ((float) ($data['salvage_value'] ?? 0) >= (float) $data['acquisition_cost']) {
            throw ValidationException::withMessages([
                'salvage_value' => [__('Salvage value must be less than the acquisition cost.')],
            ]);
        }

        return FixedAsset::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'acquisition_date' => $data['acquisition_date'],
            'acquisition_cost' => $data['acquisition_cost'],
            'salvage_value' => $data['salvage_value'] ?? 0,
            'useful_life_months' => $data['useful_life_months'],
            'asset_subject_id' => $data['asset_subject_id'],
            'depreciation_expense_subject_id' => $data['depreciation_expense_subject_id'],
            'accumulated_depreciation_subject_id' => $data['accumulated_depreciation_subject_id'],
            'created_by' => $user->id,
        ]);
    }

    public function accumulatedDepreciation(FixedAsset $asset): float
    {
        return (float) $asset->depreciationEntries()->sum('amount');
    }

    public function remainingDepreciableAmount(FixedAsset $asset): float
    {
        return max($asset->depreciableAmount() - $this->accumulatedDepreciation($asset), 0.0);
    }

    /**
     * Posts one period's depreciation. Concurrency: locks the asset row
     * before checking/writing, so two concurrent posts for the same
     * period (or two posts that would jointly exceed the depreciable cap)
     * cannot both succeed; the migration's unique constraint on
     * [fixed_asset_id, period_date] is the final DB-level collision guard.
     */
    public function postDepreciation(FixedAsset $asset, string $periodDate, User $user): FixedAssetDepreciationEntry
    {
        return DB::transaction(function () use ($asset, $periodDate, $user) {
            $locked = FixedAsset::query()->lockForUpdate()->findOrFail($asset->id);

            if ($locked->status !== FixedAssetStatus::ACTIVE) {
                throw ValidationException::withMessages([
                    'asset' => [__('This asset is disposed and can no longer be depreciated.')],
                ]);
            }

            $alreadyPosted = FixedAssetDepreciationEntry::where('fixed_asset_id', $locked->id)
                ->whereDate('period_date', $periodDate)
                ->exists();
            if ($alreadyPosted) {
                throw ValidationException::withMessages([
                    'period_date' => [__('Depreciation has already been posted for this period.')],
                ]);
            }

            $amount = min($locked->monthlyDepreciation(), $this->remainingDepreciableAmount($locked));
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'asset' => [__('This asset is already fully depreciated.')],
                ]);
            }

            $document = DocumentService::createDocument($user, [
                'date' => $periodDate,
                'title' => __('Depreciation for').' '.$locked->name,
                'number' => null,
            ], [
                ['subject_id' => $locked->depreciation_expense_subject_id, 'desc' => __('Depreciation expense'), 'value' => -$amount],
                ['subject_id' => $locked->accumulated_depreciation_subject_id, 'desc' => __('Accumulated depreciation'), 'value' => $amount],
            ]);
            DocumentService::changeDocumentStatus($document, $user, 'approved');

            return FixedAssetDepreciationEntry::create([
                'fixed_asset_id' => $locked->id,
                'period_date' => $periodDate,
                'amount' => $amount,
                'document_id' => $document->id,
            ]);
        });
    }

    public function dispose(FixedAsset $asset, string $date): FixedAsset
    {
        $asset->update([
            'status' => FixedAssetStatus::DISPOSED,
            'disposed_at' => $date,
        ]);

        return $asset->fresh();
    }
}
