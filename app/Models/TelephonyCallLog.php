<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelephonyCallLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'caller_number',
        'customer_id',
        'channel',
        'extension',
        'call_status',
        'customer_snapshot',
        'notes',
    ];

    protected $casts = [
        'customer_snapshot' => 'array',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
