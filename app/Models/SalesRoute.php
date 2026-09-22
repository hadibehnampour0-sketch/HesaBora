<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesRoute extends Model
{
    protected $fillable = [
        'company_id',
        'title',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $route) {
            $route->company_id ??= getActiveCompany();
        });
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'route_customers', 'route_id', 'customer_id')
            ->withPivot(['visit_priority', 'preferred_visit_days'])
            ->orderByPivot('visit_priority');
    }

    public function routeCustomers(): HasMany
    {
        return $this->hasMany(RouteCustomer::class, 'route_id');
    }

    public function visitPlans(): HasMany
    {
        return $this->hasMany(VisitPlan::class, 'route_id');
    }
}
