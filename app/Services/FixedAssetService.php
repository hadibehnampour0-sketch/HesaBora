<?php

namespace App\Services;

use App\Enums\FixedAssetStatus;
use App\Models\AssetMaintenance;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciationEntry;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * P3.5 (Fixed Assets & Maintenance Logbook Engine).
 * Straight-line depreciation + Comprehensive Equipment Maintenance Logbook with AI TCO Intelligence.
 */
class FixedAssetService
{
    /**
     * Automatically resolves or creates default accounting subjects for equipment and maintenance.
     * Prevents small business / shop owners from getting blocked by accounting code complexity.
     *
     * @return array{
     *     asset_subject_id: int,
     *     depreciation_expense_subject_id: int,
     *     accumulated_depreciation_subject_id: int,
     *     maintenance_expense_subject_id: int,
     *     payment_subject_id: int
     * }
     */
    public function resolveDefaultSubjects(int $companyId): array
    {
        $assetSub = $this->resolveOrCreateSubject($companyId, 'دارایی‌های ثابت مشهود (تجهیزات و اثاثیه)', '10701');
        $depExpenseSub = $this->resolveOrCreateSubject($companyId, 'هزینه استهلاک دارایی‌های ثابت', '80301');
        $accumDepSub = $this->resolveOrCreateSubject($companyId, 'استهلاک انباشته دارایی‌های ثابت', '10801');
        $maintExpenseSub = $this->resolveOrCreateSubject($companyId, 'هزینه تعمیر و نگهداری اموال و تجهیزات', '80201');

        // Resolve cash or bank for payment credit
        $cashSub = Subject::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($q) {
                $q->where('code', 'like', '101%')
                  ->orWhere('code', 'like', '102%')
                  ->orWhere('name', 'like', '%صندوق%')
                  ->orWhere('name', 'like', '%بانک%');
            })
            ->first();

        if (! $cashSub) {
            $cashSub = $this->resolveOrCreateSubject($companyId, 'صندوق اصلی شرکت', '10101');
        }

        return [
            'asset_subject_id' => $assetSub->id,
            'depreciation_expense_subject_id' => $depExpenseSub->id,
            'accumulated_depreciation_subject_id' => $accumDepSub->id,
            'maintenance_expense_subject_id' => $maintExpenseSub->id,
            'payment_subject_id' => $cashSub->id,
        ];
    }

    protected function resolveOrCreateSubject(int $companyId, string $name, string $code): Subject
    {
        $subject = Subject::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($name, $code) {
                $q->where('code', $code)->orWhere('name', $name);
            })
            ->first();

        if ($subject) {
            return $subject;
        }

        return Subject::create([
            'company_id' => $companyId,
            'name' => $name,
            'code' => $code,
            'parent_id' => null,
        ]);
    }

    public function register(array $data, User $user): FixedAsset
    {
        $companyId = (int) (getActiveCompany() ?: ($data['company_id'] ?? 1));
        $defaults = $this->resolveDefaultSubjects($companyId);

        if ((float) ($data['salvage_value'] ?? 0) >= (float) $data['acquisition_cost']) {
            throw ValidationException::withMessages([
                'salvage_value' => [__('Salvage value must be less than the acquisition cost.')],
            ]);
        }

        $code = trim($data['code'] ?? '');
        if (empty($code)) {
            $nextNum = (FixedAsset::withoutGlobalScopes()->where('company_id', $companyId)->max('id') ?? 0) + 1;
            $code = 'AST-' . str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);
        }

        return FixedAsset::create([
            'company_id' => $companyId,
            'code' => $code,
            'name' => $data['name'],
            'category' => $data['category'] ?? 'equipment',
            'serial_or_plate' => $data['serial_or_plate'] ?? null,
            'location' => $data['location'] ?? null,
            'description' => $data['description'] ?? null,
            'acquisition_date' => $data['acquisition_date'],
            'acquisition_cost' => $data['acquisition_cost'],
            'salvage_value' => $data['salvage_value'] ?? 0,
            'useful_life_months' => ! empty($data['useful_life_months']) ? (int) $data['useful_life_months'] : 60,
            'asset_subject_id' => ! empty($data['asset_subject_id']) ? $data['asset_subject_id'] : $defaults['asset_subject_id'],
            'depreciation_expense_subject_id' => ! empty($data['depreciation_expense_subject_id']) ? $data['depreciation_expense_subject_id'] : $defaults['depreciation_expense_subject_id'],
            'accumulated_depreciation_subject_id' => ! empty($data['accumulated_depreciation_subject_id']) ? $data['accumulated_depreciation_subject_id'] : $defaults['accumulated_depreciation_subject_id'],
            'created_by' => $user->id,
        ]);
    }

    /**
     * Record a maintenance, service, or repair event for a specific equipment/asset.
     * Posts a balanced accounting document (Debit Maintenance Expense, Credit Cash/Bank)
     * and attaches the event to the asset's logbook.
     */
    public function recordMaintenance(
        FixedAsset $asset,
        array $data,
        User $user,
        ?UploadedFile $attachment = null
    ): AssetMaintenance {
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['مبلغ هزینه تعمیر یا نگهداری باید بزرگتر از صفر باشد.'],
            ]);
        }

        $companyId = $asset->company_id;
        $defaults = $this->resolveDefaultSubjects($companyId);

        $attachmentPath = null;
        if ($attachment && $attachment->isValid()) {
            $attachmentPath = $attachment->store('asset_invoices/' . $companyId, 'public');
        }

        return DB::transaction(function () use ($asset, $data, $user, $amount, $companyId, $defaults, $attachmentPath) {
            $lockedAsset = FixedAsset::query()->lockForUpdate()->findOrFail($asset->id);

            $date = $data['date'] ?? now()->toDateString();
            $title = $data['title'] ?? ('تعمیر و سرویس ' . $lockedAsset->name);
            $type = $data['maintenance_type'] ?? 'repair';

            // 1. Determine Expense Account
            // If capital improvement, we add to asset subject directly; otherwise normal maintenance expense
            $isCapital = ($type === 'capital_improvement');
            $expenseSubjectId = $isCapital
                ? $lockedAsset->asset_subject_id
                : $defaults['maintenance_expense_subject_id'];

            // 2. Determine Payment Account (Credit)
            $paymentSubjectId = ! empty($data['payment_subject_id'])
                ? (int) $data['payment_subject_id']
                : $defaults['payment_subject_id'];

            // 3. Post Balanced Accounting Document
            $docTitle = 'هزینه ' . $title . ' — ' . $lockedAsset->name . ($lockedAsset->serial_or_plate ? " ({$lockedAsset->serial_or_plate})" : '');
            $document = DocumentService::createDocument($user, [
                'company_id' => $companyId,
                'date' => $date,
                'title' => $docTitle,
                'number' => null,
            ], [
                [
                    'subject_id' => $expenseSubjectId,
                    'desc' => $docTitle,
                    'value' => -$amount, // Debit (Expense)
                ],
                [
                    'subject_id' => $paymentSubjectId,
                    'desc' => 'پرداخت بابت ' . $docTitle,
                    'value' => $amount, // Credit (Cash/Bank)
                ],
            ]);

            DocumentService::changeDocumentStatus($document, $user, 'approved');

            // If capital improvement, also reflect on asset acquisition cost
            if ($isCapital) {
                $lockedAsset->acquisition_cost = (float) $lockedAsset->acquisition_cost + $amount;
                $lockedAsset->save();
            }

            // 4. Create Asset Maintenance Record
            return AssetMaintenance::create([
                'company_id' => $companyId,
                'fixed_asset_id' => $lockedAsset->id,
                'title' => $title,
                'maintenance_type' => $type,
                'date' => $date,
                'amount' => $amount,
                'service_provider_id' => ! empty($data['service_provider_id']) ? $data['service_provider_id'] : null,
                'odometer_or_hours' => ! empty($data['odometer_or_hours']) ? (int) $data['odometer_or_hours'] : null,
                'description' => $data['description'] ?? null,
                'invoice_attachment' => $attachmentPath,
                'document_id' => $document->id,
                'created_by' => $user->id,
            ]);
        });
    }

    public function deleteMaintenance(FixedAsset $asset, AssetMaintenance $maintenance, User $user): bool
    {
        return DB::transaction(function () use ($asset, $maintenance) {
            if ($maintenance->invoice_attachment && Storage::disk('public')->exists($maintenance->invoice_attachment)) {
                Storage::disk('public')->delete($maintenance->invoice_attachment);
            }

            return $maintenance->delete();
        });
    }

    public function accumulatedDepreciation(FixedAsset $asset): float
    {
        return (float) $asset->depreciationEntries()->sum('amount');
    }

    public function remainingDepreciableAmount(FixedAsset $asset): float
    {
        return max($asset->depreciableAmount() - $this->accumulatedDepreciation($asset), 0.0);
    }

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
