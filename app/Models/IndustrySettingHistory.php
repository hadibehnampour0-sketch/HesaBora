<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;

class IndustrySettingHistory extends Model
{
    protected $table = 'industry_setting_histories';

    public $timestamps = false;

    protected $fillable = ['company_id', 'profile_key', 'setting_key', 'action', 'old_value', 'new_value', 'changed_by', 'created_at'];

    protected $casts = [

        'created_at' => 'datetime',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $row) {
            $row->company_id ??= getActiveCompany();
        });
    }
}
