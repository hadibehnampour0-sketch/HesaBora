<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxFilingRecord extends Model
{
    protected $fillable = [
        'company_id',
        'jalali_year',
        'quarter',
        'filed_at',
        'filed_by',
        'notes',
    ];

    protected $casts = [
        'jalali_year' => 'integer',
        'quarter' => 'integer',
        'filed_at' => 'datetime',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $record) {
            $record->company_id ??= getActiveCompany();
        });
    }

    public function filedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by');
    }
}
