<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewayConfig extends Model
{
    protected $fillable = [
        'company_id',
        'gateway',
        'merchant_id',
        'api_key',
        'is_sandbox',
        'is_active',
        'default_subject_id',
    ];

    protected $casts = [
        'is_sandbox' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (PaymentGatewayConfig $config) {
            $config->company_id ??= getActiveCompany();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function defaultSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'default_subject_id');
    }
}
