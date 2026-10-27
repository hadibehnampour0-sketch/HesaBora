<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradeExchange extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'customer_id',
        'title',
        'status',
        'settlement_type',
        'settlement_notes',
        'settled_at',
        'created_by',
        'notes',
        'document_id',
    ];

    protected $casts = [
        'settled_at' => 'datetime',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (TradeExchange $exchange) {
            $exchange->company_id ??= getActiveCompany();
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TradeExchangeItem::class, 'trade_exchange_id');
    }

    public function itemsOut(): HasMany
    {
        return $this->hasMany(TradeExchangeItem::class, 'trade_exchange_id')->where('direction', 'out');
    }

    public function itemsIn(): HasMany
    {
        return $this->hasMany(TradeExchangeItem::class, 'trade_exchange_id')->where('direction', 'in');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'partially_settled']);
    }

    public function calculateValuationSummary(): array
    {
        $items = $this->items()->get();

        $outValue = 0.0;
        $inValue = 0.0;
        $openOutItemsCount = 0;
        $openInItemsCount = 0;

        foreach ($items as $item) {
            $effectiveQty = max(0, $item->quantity - $item->returned_quantity);
            $itemTotal = $effectiveQty * $item->unit_price;

            if ($item->direction === 'out') {
                $outValue += $itemTotal;
                if ($effectiveQty > 0) {
                    $openOutItemsCount++;
                }
            } else {
                $inValue += $itemTotal;
                if ($effectiveQty > 0) {
                    $openInItemsCount++;
                }
            }
        }

        return [
            'total_out_value' => $outValue,
            'total_in_value' => $inValue,
            'net_valuation' => $outValue - $inValue, // positive: counterparty owes us; negative: we owe counterparty
            'open_out_count' => $openOutItemsCount,
            'open_in_count' => $openInItemsCount,
        ];
    }
}
