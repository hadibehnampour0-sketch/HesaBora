<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankStatementLine extends Model
{
    protected $fillable = [
        'bank_statement_id',
        'bank_account_id',
        'date',
        'amount',
        'description',
        'reference',
        'dedupe_hash',
        'reconciled_transaction_id',
        'reconciled_at',
        'reconciled_by',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'reconciled_at' => 'datetime',
    ];

    public function statement()
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function reconciledTransaction()
    {
        return $this->belongsTo(Transaction::class, 'reconciled_transaction_id');
    }

    public function reconciledByUser()
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function isReconciled(): bool
    {
        return $this->reconciled_transaction_id !== null;
    }
}
