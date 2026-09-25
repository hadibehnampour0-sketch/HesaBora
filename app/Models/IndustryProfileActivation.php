<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;

class IndustryProfileActivation extends Model
{
    protected $table = 'industry_profile_activations';

    protected $fillable = ['company_id', 'profile_key', 'activated_at'];

    protected $casts = [
        'activated_at' => 'datetime',

    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $row) {
            $row->company_id ??= getActiveCompany();
        });
    }
}
