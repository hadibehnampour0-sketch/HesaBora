<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettlementInstallment extends Model
{
    protected $fillable = [
        'obligation_id',
        'due_date',
        'amount',
    ];

    protected $casts = [
        'due_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function obligation()
    {
        return $this->belongsTo(SettlementObligation::class, 'obligation_id');
    }

    /**
     * Allocations against this installment, through the same polymorphic
     * ledger Invoice payments use (D-070) -- not a second allocation system.
     */
    public function allocations()
    {
        return $this->morphMany(PaymentAllocation::class, 'allocatable');
    }
}
