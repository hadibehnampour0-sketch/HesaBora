<?php

namespace App\Models;

use App\Enums\InvoiceType;
use App\Enums\RecurringFrequency;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecurringInvoiceTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'invoice_type',
        'customer_id',
        'warehouse_id',
        'items',
        'frequency',
        'interval',
        'start_date',
        'end_date',
        'next_run_date',
        'last_generated_at',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'invoice_type' => InvoiceType::class,
        'frequency' => RecurringFrequency::class,
        'items' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'next_run_date' => 'date',
        'last_generated_at' => 'date',
        'is_active' => 'boolean',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $template) {
            $template->company_id ??= getActiveCompany();
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function generatedInvoices()
    {
        return $this->hasMany(Invoice::class, 'recurring_invoice_template_id');
    }
}
