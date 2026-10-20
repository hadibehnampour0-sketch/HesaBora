<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficialInquiryLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'inquiry_type',
        'query_term',
        'query_payload',
        'is_matched',
        'status',
        'response_data',
        'error_message',
    ];

    protected $casts = [
        'query_payload' => 'array',
        'response_data' => 'array',
        'is_matched' => 'boolean',
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
}
