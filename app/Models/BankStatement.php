<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankStatement extends Model
{
    protected $fillable = [
        'bank_account_id',
        'original_filename',
        'lines_count',
        'duplicate_lines_count',
        'imported_by',
        'company_id',
    ];

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function lines()
    {
        return $this->hasMany(BankStatementLine::class);
    }

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
