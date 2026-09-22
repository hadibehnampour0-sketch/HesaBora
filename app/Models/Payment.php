<?php

namespace App\Models;

use App\Models\Concerns\HasSyncEnvelope;
use App\Models\Concerns\SyncsPaymentsToOutbox;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasSyncEnvelope, SyncsPaymentsToOutbox;

    protected $fillable = [
        'amount',
        'date',
        'reference_number',
        'description',
        'payer_id',
        'document_id',
        'settlement_subject_id',
        'creator_id',
        'invoice_id',
        'cheque_id',
        'sync_version',
        'sync_status',
        'origin_device_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
    ];

    public function payer()
    {
        return $this->belongsTo(Customer::class, 'payer_id');
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function settlementSubjectRelation()
    {
        return $this->belongsTo(Subject::class, 'settlement_subject_id');
    }

    public function settlementSubject(): ?Subject
    {
        if ($this->settlement_subject_id) {
            return $this->settlementSubjectRelation;
        }

        $customerSubjectId = (int) ($this->payer?->subject?->id ?? $this->payer?->subject_id);
        $transactions = $this->document?->transactions ?? collect();

        return $transactions->first(fn ($transaction) => (int) $transaction->subject_id !== $customerSubjectId)?->subject;
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function cheque()
    {
        return $this->belongsTo(Cheque::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * Organization Authorization & Workflow Governance phase (continuation,
     * Workstream J/U): `payments` has no `company_id` column of its own and
     * no `FiscalYearScope`, so a route-model-bound `Payment $payment` (e.g.
     * `payments/{payment}/allocations`) resolves regardless of company --
     * found adversarially while building the permission matrix. Callers
     * that route-bind a Payment directly (not nested under an
     * already-scoped `{invoice}`, which transitively guarantees the match)
     * MUST check this against `getActiveCompany()` before acting on it.
     * Tries every relation that could carry the company, in the order a
     * real Payment is most likely to have them populated.
     */
    public function resolvedCompanyId(): ?int
    {
        return $this->document?->company_id
            ?? $this->invoice?->company_id
            ?? $this->cheque?->company_id
            ?? $this->payer?->company_id;
    }

    /**
     * D-078 decision 3: distinct from `operation_id` (a sync attempt's own
     * identity). This identifies the underlying economic event itself and
     * never changes for the life of the record -- it is the same `uuid`
     * every other distributed entity already carries via HasSyncEnvelope
     * (stage 2.3), named here to match the domain vocabulary the Decision
     * Log uses, not a second column.
     */
    public function getFinancialEventUuidAttribute(): ?string
    {
        return $this->uuid;
    }
}
