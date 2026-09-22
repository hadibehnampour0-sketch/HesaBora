<?php

namespace App\Services;

use App\Enums\DailySettlementStatus;
use App\Models\DailySettlement;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * P11 (Field Sales). Cash/POS/cheque collected is deliberately a MANUAL
 * entry by the rep/driver, not derived from accounting records -- that is
 * the entire point of a reconciliation (comparing a physical count
 * against what the system expects), and this app's chart of accounts has
 * no reliable, company-independent way to bucket a payment as
 * "cash" vs. "POS" vs. "cheque" purely from its settlement subject.
 * `suggestedTotalInvoiced()` is the one figure that IS safely derivable
 * (Invoice.creator_id + date), offered as a cross-check starting point.
 */
class DailySettlementService
{
    public function suggestedTotalInvoiced(User $user, Carbon $date): float
    {
        return (float) Invoice::where('creator_id', $user->id)
            ->whereDate('date', $date->toDateString())
            ->sum('amount');
    }

    public function createOrUpdateDraft(User $user, Carbon $date, array $collected): DailySettlement
    {
        $existing = DailySettlement::where('user_id', $user->id)
            ->whereDate('settlement_date', $date->toDateString())
            ->first();

        if ($existing && $existing->status !== DailySettlementStatus::DRAFT) {
            throw ValidationException::withMessages([
                'settlement' => [__('This settlement has already been submitted and can no longer be edited.')],
            ]);
        }

        $data = array_merge($collected, [
            'user_id' => $user->id,
            'settlement_date' => $date->toDateString(),
            'total_invoiced_amount' => $this->suggestedTotalInvoiced($user, $date),
        ]);

        if ($existing) {
            $existing->update($data);

            return $existing->fresh();
        }

        return DailySettlement::create($data);
    }

    public function submit(DailySettlement $settlement): void
    {
        if ($settlement->status !== DailySettlementStatus::DRAFT) {
            throw ValidationException::withMessages([
                'settlement' => [__('Only a draft settlement can be submitted.')],
            ]);
        }

        $settlement->update(['status' => DailySettlementStatus::SUBMITTED, 'submitted_at' => now()]);
    }

    public function approve(DailySettlement $settlement, User $approver): void
    {
        if ($settlement->status !== DailySettlementStatus::SUBMITTED) {
            throw ValidationException::withMessages([
                'settlement' => [__('Only a submitted settlement can be approved.')],
            ]);
        }

        $settlement->update([
            'status' => DailySettlementStatus::APPROVED,
            'approved_at' => now(),
            'approved_by' => $approver->id,
        ]);
    }
}
