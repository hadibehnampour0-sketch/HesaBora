<?php

namespace App\Models;

use App\Models\Concerns\HasSyncEnvelope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory, HasSyncEnvelope;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'subject_id',
        'document_id',
        'user_id',
        'desc',
        'value',
        'currency_id',
        'foreign_value',
        'exchange_rate',
        'sync_version',
        'sync_status',
        'origin_device_id',
    ];


    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the document associated with the transaction.
     */
    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the user associated with the transaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * P3.1: analytic dimensions (Branch/Project/Cost Center/Sales Channel)
     * tagged on this ledger line -- orthogonal to which subject (account)
     * was affected. See AnalyticDimension's own docblock for why this is
     * transaction-level and many-to-many rather than a single FK.
     */
    public function analyticDimensions()
    {
        return $this->belongsToMany(AnalyticDimension::class, 'analytic_dimension_transaction');
    }

    /**
     * P3.4: the currency this line was originally denominated in, if not
     * the company's base currency. `value` is always the base-currency
     * posted amount (untouched by this) -- `currency`/`foreign_value`/
     * `exchange_rate` are purely additive metadata.
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function getDebitAttribute()
    {
        return $this->value < 0 ? formatNumber(-1 * $this->value) : '';
    }

    public function getCreditAttribute()
    {
        return $this->value > 0 ? formatNumber($this->value) : '';
    }
}
