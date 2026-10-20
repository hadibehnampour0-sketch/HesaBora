<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInvoiceExtraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'file_path',
        'original_filename',
        'file_type',
        'status',
        'vendor_name',
        'vendor_national_code',
        'vendor_phone',
        'invoice_number',
        'invoice_date',
        'subtotal',
        'vat_amount',
        'total_amount',
        'confidence_score',
        'raw_extracted_data',
        'matched_customer_id',
        'converted_invoice_id',
        'converted_at',
        'reviewer_id',
        'rejection_reason',
    ];

    protected $casts = [
        'subtotal' => 'integer',
        'vat_amount' => 'integer',
        'total_amount' => 'integer',
        'confidence_score' => 'float',
        'raw_extracted_data' => 'array',
        'converted_at' => 'datetime',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function matchedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'matched_customer_id');
    }

    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }
}
