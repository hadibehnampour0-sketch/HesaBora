<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;

class IndustrySettingValue extends Model
{
    protected $table = 'industry_setting_values';

    protected $fillable = ['company_id', 'profile_key', 'setting_key', 'value', 'confirmed_at', 'confirmed_by', 'updated_by'];

    protected $casts = [

        'confirmed_at' => 'datetime',

    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $row) {
            $row->company_id ??= getActiveCompany();
        });
    }
}
