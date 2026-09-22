<?php

namespace App\Models;

use App\Models\Concerns\HasSyncEnvelope;
use App\Models\Concerns\SyncsPaymentAllocationsToOutbox;
use Illuminate\Database\Eloquent\Model;

class PaymentAllocation extends Model
{
    use HasSyncEnvelope, SyncsPaymentAllocationsToOutbox;

    protected $fillable = [
        'payment_id',
        'allocatable_type',
        'allocatable_id',
        'amount',
        'allocated_at',
        'reference',
        'created_by',
        'sync_version',
        'sync_status',
        'origin_device_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'allocated_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Polymorphic target of this allocation: an Invoice or a
     * SettlementInstallment (D-070) share this one allocation ledger.
     */
    public function allocatable()
    {
        return $this->morphTo();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * P10 sync-engine hardening: `payment_allocations` has no `company_id`
     * column of its own and no `FiscalYearScope`, mirroring Payment's own
     * `resolvedCompanyId()` (see that method's docblock). Needed so
     * SyncPilotService::apply() can reject a cross-company uuid collision
     * instead of treating another company's allocation as if it were the
     * pushing device's own.
     */
    public function resolvedCompanyId(): ?int
    {
        return $this->payment?->resolvedCompanyId();
    }
}
