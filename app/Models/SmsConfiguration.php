<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsConfiguration extends Model
{
    protected $fillable = [
        'company_id',
        'provider',
        'api_key',
        'sender_number',
        'is_active',
        'auto_invoice_approved',
        'auto_payment_received',
        'auto_cheque_due_reminder',
        'auto_debt_reminder',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'is_active' => 'boolean',
        'auto_invoice_approved' => 'boolean',
        'auto_payment_received' => 'boolean',
        'auto_cheque_due_reminder' => 'boolean',
        'auto_debt_reminder' => 'boolean',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $config) {
            $config->company_id ??= getActiveCompany();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
