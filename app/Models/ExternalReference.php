<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;

class ExternalReference extends Model
{
    protected $table = 'external_references';

    protected $fillable = ['company_id', 'source_system', 'external_id', 'entity_type', 'entity_id'];

    protected $casts = [
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $row) {
            $row->company_id ??= getActiveCompany();
        });
    }
}
