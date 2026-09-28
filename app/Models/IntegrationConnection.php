<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationConnection extends Model
{
    protected $fillable = [
        'company_id',
        'source_system',
        'name',
        'webhook_token',
        'credentials',
        'default_warehouse_id',
        'default_customer_group_id',
        'price_tolerance_percent',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'price_tolerance_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $connection) {
            $connection->company_id ??= getActiveCompany();
            $connection->webhook_token ??= bin2hex(random_bytes(24));
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'default_customer_group_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
