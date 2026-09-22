<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * P6 (API v1 Platform). One registration = one URL subscribed to a set of
 * event type strings for one company. `secret` signs every outbound
 * delivery (see WebhookService::signature()) so the receiving endpoint can
 * verify the payload actually came from this app.
 */
class Webhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'url',
        'secret',
        'event_types',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'event_types' => 'array',
        'is_active' => 'boolean',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $webhook) {
            $webhook->company_id ??= getActiveCompany();
        });
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
